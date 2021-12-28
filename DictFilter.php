<?php

namespace app\Service\TextWord;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use app\Service\TextWord\Synonym\WordText;
use function array_flip;
use function array_key_last;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function count;
use function implode;
use function str_starts_with;
use function strlen;

/**
 * @implements \IteratorAggregate<string, WordText>
 */
class DictFilter implements \IteratorAggregate
{
    private \Generator $words;

    static ?array $CUT_WORDS = null;

    protected array $buffer = [];

    public function __construct(\Generator $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    protected function initDict()
    {
        self::$CUT_WORDS = array_flip([...TextConstants::SYMBOL_CUT, ...TextConstants::SYMBOL_SEG]);
    }

    protected function joinWord(array $item): string
    {
        return implode(' ', array_map(fn($v) => $v['text'], $item));
    }

    public function getIterator(): \Traversable
    {
        $this->buffer = [];
        $goBackWord = null;
        $matchCount = 0;
        while ($this->words->valid()) {
            $item = $this->words->current();
            $this->words->next();
            ['type' => $type, 'text' => $text] = $item;
//            dump("input: {$text}");

            if (0 === $matchCount
                && (
                    TextConstants::TYPE_LF === $type
                    || (TextConstants::TYPE_SYMBOL === $type && isset(self::$CUT_WORDS[$text]))
                )
            ) {
                yield from $this->buffer;
                if (null !== $goBackWord) {
                    yield $goBackWord;
                    $goBackWord = null;
                }
                yield $item;
                $this->buffer = [];
                continue;
            } else {
                $this->buffer[] = $item;
            }

            QUERY_MATCH:
            $wordsText = $this->joinWord($this->buffer);
            $queryText  = AmazonWordDictModel::buildQueryString($wordsText);
//            dump("> {$wordsText} > {$queryText}");
            if (empty($queryText)) {
//                dump("> skip");
                continue;
            }
            $matchItems = AmazonWordDictModel::findPhraseRaw($queryText, 2);
            $matchCount = $matchItems->count();
//            dump("> matchCount: {$matchCount}");

            if (0 === $matchCount) {
                if (null !== $goBackWord) {
                    // 回退后是错误的
                    throw new \LogicException('回退后不可能无法匹配');
                } elseif (count($this->buffer) > 1) {
                    GO_BACK:
                    // 前一次是匹配，叠加后不匹配，回退
//                    dump("> go back");
                    $goBackWord  = array_pop($this->buffer);
                    $wordsText = $this->joinWord($this->buffer);
                    $queryText  = AmazonWordDictModel::buildQueryString($wordsText);
                    $_word = AmazonWordDictModel::findEqualRaw($queryText);
                    if (null === $_word) {
                        // 无法匹配，全部弹出
                        yield from $this->buffer;
                        $this->buffer = [$goBackWord];
                        $goBackWord = null;
                        goto QUERY_MATCH;
                    }
                    $matchItems = [$_word];
                    goto SUCCESS;
                } else {
                    yield array_shift($this->buffer);
                }
            } elseif (1 === $matchCount) {
                if ($queryText === $matchItems[0]['query']) {
//                    dump('>-prefix:equal');
                    // 完全匹配
                    goto SUCCESS;
                } elseif (str_starts_with($matchItems[0]['query'], $queryText)) {
//                    dump('>-prefix');
                    // 前缀匹配
                    $matchQuery = $matchItems[0]['query'];
                    $_tmpBuffer = [];
                    // 前缀匹配，尝试完全匹配
                    while ($this->words->valid()) {
                        $item = $this->words->current();
                        $this->words->next();
                        $_tmpBuffer[] = $item;
                        $wordsText = $this->joinWord(array_merge($this->buffer, $_tmpBuffer));
                        $queryText = AmazonWordDictModel::buildQueryString($wordsText);
//                        dump(">-prefix: {$wordsText} > {$queryText}");
                        $matchPos = strpos($matchQuery, $queryText);
                        if (0 !== $matchPos) {
//                            dump('>-prefix:fail');
                            // 无法匹配，全部弹出
                            yield from $this->buffer;
                            $this->buffer = $_tmpBuffer;
                            continue 2;
                        }
                        if (strlen($matchQuery) === strlen($queryText)) {
//                            dump('>-prefix:success');
                            // 完全匹配，作为一个整体
                            $this->buffer = array_merge($this->buffer, $_tmpBuffer);
                            goto SUCCESS;
                        }
                    }
                    if (true === $this->words->valid()) {
                        throw new \LogicException('不可预知的情况，执行异常的分支');
                    }
                    // 没有可匹配的词，弹出
                    yield from $this->buffer;
                    $this->buffer = $_tmpBuffer;
                    continue;
                } else {
                    // 存在多个匹配
                    continue;
                }

                SUCCESS: {
                    if (count($matchItems) > 1) {
                        throw new \LogicException('不允许存在多个匹配值');
                    }
                    if (count($this->buffer) > 1) {
                        // 处理符号误粘连问题
                        if ($this->buffer[0]['type'] !== TextConstants::TYPE_WORD) {
                            yield array_shift($this->buffer);
                        } elseif (
                            $this->buffer[array_key_last($this->buffer)]['type'] === TextConstants::TYPE_SYMBOL
                        ) {
                            $_tmpSymbolLast = array_pop($this->buffer);
                        }
                    }
                    // 匹配成功
                    $text         = (new WordsCombineText($this->buffer))->build();
                    $this->buffer = [];

                    $model       = $matchItems[0];
                    if ($model->isBad()) {
                        $stat = WordFilterEnum::_BAD;
                    } elseif ($model->isWarn()) {
                        $stat = WordFilterEnum::_WARN;
                    } else {
                        $stat = 0;
                    }
                    yield [
                        'type' => TextConstants::TYPE_WORD,
                        'text' => $text,
                        'stat' => $stat,
                    ];
                    if (isset($_tmpSymbolLast)) {
                        yield $_tmpSymbolLast;
                        $_tmpSymbolLast = null;
                    }
                    if (null !== $goBackWord) {
                        yield $goBackWord;
                        $goBackWord = null;
                    }
                }
            } else if (!$this->words->valid() && $matchCount > 1) {
                if (count($this->buffer) > 2) {
                    goto GO_BACK;
                } elseif (1 === count($this->buffer)) {
                    yield from $this->buffer;
                    $this->buffer = [];
                }
            }
        }
        if (!empty($this->buffer)) {
            yield from $this->buffer;
        }
    }
}
