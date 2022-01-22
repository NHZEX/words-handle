<?php

namespace app\Service\TextWord\Dict;

use app\Model\AmazonWordDictModel;
use app\Service\TextWord\TextConstants;
use app\Service\TextWord\TextNode;
use app\Service\TextWord\WordsCombineText;
use Traversable;
use function array_flip;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function implode;
use function json_decode_ex;
use function strlen;
use function strpos;
use function strtoupper;

class DictDecoratorFilter implements \IteratorAggregate
{
    /**
     * @var \Iterator<int, TextNode>
     */
    private \Iterator $words;

    /**
     * @var array<int, TextNode>
     */
    protected array $buffer = [];

    static ?array          $CUT_WORDS = null;

    private DictDecoratorQuery $dict;

    /**
     * @param \Iterator<int, TextNode> $words
     */
    public function __construct(\Iterator $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    protected function initDict()
    {
        if (null === self::$CUT_WORDS) {
            self::$CUT_WORDS = array_flip([...TextConstants::SYMBOL_CUT, ...TextConstants::SYMBOL_SEG]);
        }

        $this->dict = new DictDecoratorQuery();
    }

    /**
     * @param array<int, TextNode> $item
     * @return string
     */
    protected function joinWord(array $item): string
    {
        return implode(' ', array_map(fn($v) => $v->text, $item));
    }

    public function getIterator(): Traversable
    {
        $this->buffer = [];
        $goBackWord = null;
        $matchCount = 0;
        while ($this->words->valid()) {
            /** @var TextNode $item */
            $item = $this->words->current();
            $this->words->next();
            // dump("input: {$text}");

            if (0 === $matchCount
                && (
                    $item->isWrap()
                    || ($item->stat !== null && $item->stat > 0)
                    || ($item->isSymbol() && isset(self::$CUT_WORDS[$item->text]))
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
            // dump("> {$wordsText} > {$queryText}");
            if (empty($queryText)) {
                // dump("> skip");
                continue;
            }
            $matchItems = $this->dict->prefixQuery($queryText, 2);
            $matchCount = count($matchItems);
            // dump("> matchCount: {$matchCount}");

            if (0 === $matchCount) {
                if (null !== $goBackWord) {
                    // 回退后是错误的
                    throw new \LogicException('回退后不可能无法匹配');
                } elseif (count($this->buffer) > 1) {
                    GO_BACK:
                    // 前一次是匹配，叠加后不匹配，回退
                    // dump("> go back");
                    $goBackWord  = array_pop($this->buffer);
                    $wordsText = $this->joinWord($this->buffer);
                    $queryText  = AmazonWordDictModel::buildQueryString($wordsText);
                    $_word = $this->dict->exactQuery($queryText, 1)[0] ?? null;
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
                    // dump('>-prefix:equal');
                    // 完全匹配
                    goto SUCCESS;
                } elseif (str_starts_with($matchItems[0]['query'], $queryText)) {
                    // dump('>-prefix');
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
                        // dump(">-prefix: {$wordsText} > {$queryText}");
                        $matchPos = strpos($matchQuery, $queryText);
                        if (0 !== $matchPos) {
                            // dump('>-prefix:fail');
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
                        if (!$this->buffer[0]->isWord()) {
                            yield array_shift($this->buffer);
                        } elseif (
                            $this->buffer[array_key_last($this->buffer)]->isSymbol()
                        ) {
                            $_tmpSymbolLast = array_pop($this->buffer);
                        }
                    }
                    // 匹配成功
                    $wct = WordsCombineText::makeFromNodes($this->buffer);
                    $text = $wct->buildString();
                    $this->buffer = [];

                    $word       = $matchItems[0];
                    yield $this->buildText($text, $word);

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

    protected function buildText(string $text, array $word): TextNode
    {
        if ($word['options'] && $_options = json_decode_ex($word['options'])) {
            $convFormat = $_options['convFormat'] ?? 0;
            if (1 === $convFormat) {
                $text = strtoupper($text);
            }
        }
        return TextNode::makeWord($text, 0);
    }
}
