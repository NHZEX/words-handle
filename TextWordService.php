<?php

namespace app\Service\TextWord;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use function count;
use function explode;
use function htmlspecialchars_decode;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;
use function Zxin\Str\str_fullwidth_to_ascii;

class TextWordService
{
    public const TYPE_WORD = 'w';
    public const TYPE_SYMBOL = 'o';
    public const TYPE_LF = 'lf';

    public function clean(string $text): string
    {
        $output = htmlspecialchars_decode($text);
        $output = strip_tags($output);
        $output = str_replace(['&nbsp;'], [''], $output);
        /**$output = preg_replace('#<br\s?/?>#', "\n", $output);**/
        $output = preg_replace("/\n{2,}/u", "\n\n", $output);
        return str_fullwidth_to_ascii($output);
    }

    public function slice(string $text): ?\Generator
    {
        $count = preg_match_all("/[a-z]+/i", $text, $matches, PREG_OFFSET_CAPTURE);

        if (empty($count)) {
            return null;
        }

        $lastPoint = 0;
        foreach ($matches[0] as $match) {
            [$word, $point] = $match;
            $len = strlen($word);
            if ($point > $lastPoint) {
                $word2 = substr($text, $lastPoint, $point - $lastPoint);
                $word2 = trim($word2, " \t\r\0\x0B");
                if (!empty($word2)) {
                    $t = explode("\n", $word2);
                    if (count($t) > 1 && $t[0] === '') {
                        // 移除切割产生的多余换行
                        unset($t[0]);
                    }
                    foreach ($t as $value) {
                        if ('' === $value) {
                            yield [
                                'type' => self::TYPE_LF,
                                'stat' => null,
                                'text' => "\n",
                            ];
                        } else {
                            yield [
                                'type' => self::TYPE_SYMBOL,
                                'stat' => null,
                                'text' => $value,
                            ];
                        }
                    }
                }
            }
            $lastPoint = $point + $len;
            yield [
                'type' => self::TYPE_WORD,
                'stat' => null,
                'text' => $word,
            ];
        }
    }

    public function filter(iterable $items, $onlyError = false): \Generator
    {
        foreach ($items as $item) {
            ['type' => $type, 'stat' => $stat, 'text' => $text] = $item;

            if (self::TYPE_WORD !== $type) {
                if (!$onlyError) {
                    yield $item;
                }
                continue;
            }

            $word = AmazonWordDictModel::findWord($text);

            if (empty($word)) {
                if (!$onlyError) {
                    yield $item;
                }
                continue;
            }

            if ($word->isBad()) {
                $item['stat'] = WordFilterEnum::_BAD;
                yield $item;
            } elseif ($word->isWarn()) {
                $item['stat'] = WordFilterEnum::_WARN;
                yield $item;
            } else if (!$onlyError) {
                yield $item;
            }
        }
    }

    public function wordTypeGuess(iterable $items): \Generator
    {
        foreach ($items as $item) {
            $isWord = preg_match('/^[\p{L}\p{Pd}\p{Zs}]+$/u', $item) > 0;
            yield [
                'type' => $isWord ? TextWordService::TYPE_WORD : TextWordService::TYPE_SYMBOL,
                'stat' => null,
                'text' => $item,
            ];
        }
    }

    public function synonym(string $word): array
    {
        return SynonymService::instance()->queryAllWithAggregation($word);
    }
}
