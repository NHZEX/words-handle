<?php

namespace app\Service\TextWord;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use app\Service\TextWord\Synonym\WordText;
use function array_map;
use function array_pop;
use function array_shift;
use function count;
use function implode;
use function in_array;

/**
 * @implements \IteratorAggregate<string, WordText>
 */
class DictFilter implements \IteratorAggregate
{
    private iterable $words;

    public function __construct(iterable $words)
    {
        $this->words = $words;
    }

    public function getIterator(): \Traversable
    {
        $bufferWords = [];
        $tmpWord = null;
        foreach ($this->words as $item) {
            ['type' => $type, 'text' => $text] = $item;

            if (TextConstants::TYPE_LF === $type
                || TextConstants::TYPE_NUMBER === $type
                || (
                    TextConstants::TYPE_SYMBOL === $type
                    && (in_array($text, TextConstants::SYMBOL_CUT) || in_array($text, TextConstants::SYMBOL_SEG))
                )
            ) {
                yield from $bufferWords;
                yield $item;
                $bufferWords = [];
                continue;
            }

            $bufferWords[] = $item;

            QUERY_WORD:
            $bufferStr     = implode(' ', array_map(fn($v) => $v['text'], $bufferWords));

            $queryText = AmazonWordDictModel::buildQueryString($bufferStr);
            $words     = AmazonWordDictModel::findPhraseRaw($queryText, 2);
            $matchCount = $words->count();
            if ($words->isEmpty()) {
                // 无有效匹配
                if (count($bufferWords) > 1) {
                    $tmpWord  = array_pop($bufferWords);
                    goto QUERY_WORD;
                } else {
                    yield array_shift($bufferWords);
                }
            } elseif (1 === $matchCount && $queryText !== $words[0]['query']) {
                // 只有一个且字符串非全等
                continue;
            } else {
                if ($matchCount > 1 && null === $tmpWord) {
                    // 存在多个匹配 可能可以优化
                    continue;
                }
                // 等于1且字符串全等
                $model       = $words[0];
                if (count($bufferWords) > 1 && $bufferWords[0]['type'] !== TextConstants::TYPE_WORD) {
                    // 处理符号误粘连问题
                    yield array_shift($bufferWords);
                }

                $text        = (new WordsCombineText($bufferWords))->build();
                $bufferWords = [];

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
                if (null !== $tmpWord) {
                    $bufferWords[] = $tmpWord;
                    $tmpWord = null;
                }
            }
        }
        if (!empty($bufferWords)) {
            yield from $bufferWords;
        }
    }
}
