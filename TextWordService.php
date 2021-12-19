<?php

namespace app\Service\TextWord;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use function array_map;
use function array_shift;
use function count;
use function explode;
use function htmlspecialchars_decode;
use function implode;
use function in_array;
use function log_debug;
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
    public const TYPE_WORD   = 'w';
    public const TYPE_SYMBOL = 'o';
    public const TYPE_LF     = 'lf';

    protected const SYMBOL_LINK       = ['/', '-', '′', '—'];
    protected const SYMBOL_BRACKETS_A = ['(', '[', '{'];
    protected const SYMBOL_BRACKETS_B = [')', ']', '}'];
    protected const SYMBOL_CUT        = [',', '.', '?', '!', ';'];
    protected const SYMBOL_SEG        = [':'];
    protected const SYMBOL_LF         = "\n";

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

    public function filterOnlyInvalid(iterable $items): \Generator
    {
        foreach ($this->filter($items) as $item) {
            ['type' => $type, 'stat' => $stat] = $item;

            if (self::TYPE_WORD !== $type) {
                continue;
            }
            if ($stat > 0) {
                yield $item;
            }
        }
    }

    public function filter(iterable $items): \Generator
    {
        $bufferWords = [];
        foreach ($items as $item) {
            ['type' => $type, 'text' => $text] = $item;
            log_debug("[$type] $text");

            if (self::TYPE_LF === $type
                || (
                    self::TYPE_SYMBOL === $type
                    && (in_array($text, self::SYMBOL_CUT) || in_array($text, self::SYMBOL_SEG))
                )
            ) {
                yield from $bufferWords;
                yield $item;
                $bufferWords = [];
                continue;
            }

            $bufferWords[] = $item;
            $bufferStr     = implode(' ', array_map(fn($v) => $v['text'], $bufferWords));

            $queryText = AmazonWordDictModel::buildQueryString($bufferStr);
            $words     = AmazonWordDictModel::findPhraseRaw($queryText, 2);
            if ($words->isEmpty()) {
                // 无有效匹配
                yield array_shift($bufferWords);
                continue;
            } elseif ($words->count() > 1) {
                // 存在多个匹配 可能可以优化
                continue;
            } elseif ($queryText !== $words[0]['query']) {
                // 等于1且字符串非全等
                continue;
            } else {
                // 等于1且字符串全等
                $model       = $words[0];
                $text        = $this->wordsCombine($bufferWords);
                $bufferWords = [];

                if ($model->isBad()) {
                    $stat = WordFilterEnum::_BAD;
                } elseif ($model->isWarn()) {
                    $stat = WordFilterEnum::_WARN;
                } else {
                    $stat = 0;
                }
                yield [
                    'type' => self::TYPE_WORD,
                    'text' => $text,
                    'stat' => $stat,
                ];
            }
        }
        if (!empty($bufferWords)) {
            yield from $bufferWords;
        }
    }

    public function wordsCombine(iterable $items): string
    {
        // todo 特殊处理 引号，括号
        $text = '';
        $len  = count($items);
        foreach ($items as $i => $word) {
            ['text' => $wt] = $word;
            if ($i === $len - 1) {
                $text .= $wt;
            } elseif (self::TYPE_LF === $word['type'] || self::TYPE_LF === $items[$i + 1]['type']) {
                // 解决：换行
                $text .= $wt;
            } elseif (self::TYPE_SYMBOL === $word['type'] && in_array($wt, self::SYMBOL_LINK)) {
                $text .= $wt;
            } elseif (self::TYPE_SYMBOL === $items[$i + 1]['type']) {
                // 解决：引号、连接符
                $text .= $wt;
            } else {
                $text .= $wt . ' ';
            }
        }
        return $text;
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
