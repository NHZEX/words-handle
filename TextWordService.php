<?php

namespace app\Service\TextWord;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use function array_map;
use function array_shift;
use function bin2hex;
use function count;
use function htmlspecialchars_decode;
use function implode;
use function in_array;
use function mb_check_encoding;
use function preg_match;
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
    public const TYPE_NUMBER = 'n';
    public const TYPE_SYMBOL = 'o';
    public const TYPE_LF     = 'lf';

    protected const SYMBOL_LINK       = ['/', '-', '′', '—', '=', '*', '≈'];
    protected const SYMBOL_BRACKETS_A = ['(', '[', '{'];
    protected const SYMBOL_BRACKETS_B = [')', ']', '}'];
    protected const SYMBOL_CUT        = [',', '.', '?', '!', ';'];
    protected const SYMBOL_SEG        = [':'];
    protected const SYMBOL_LF         = "\n";

    public function clean(string $text): string
    {
        $output = htmlspecialchars_decode($text);
        $output = strip_tags($output);
        /**$output = preg_replace('#<br\s?/?>#', "\n", $output);**/
        $output = $this->filterSymbol(str_fullwidth_to_ascii($output));
        return preg_replace("/\n{2,}/u", "\n\n", $output);
    }

    public function filterSymbol(string $input): string
    {
        return str_replace([
            '&nbsp;',
            "\xc2\xa0", // NBSP
            '、',
            '。',
            '’',
            '“',
            '“',
        ], [
            ' ',
            ' ',
            ',',
            '.',
            '\'',
            '"',
            '"',
        ], $input);
    }

    public function slice(string $text): ?\Generator
    {
        $count = preg_match_all(
            "/(?<w>[\p{Ll}\p{Lu}\p{Lt}]+)|(?<s>[\p{P}\p{S}])|(?<n>\p{N}+(?:\.\p{N}+)?)|(?<lf>\n)/u",
            $text,
            $matches,
            PREG_OFFSET_CAPTURE
        );

        if (empty($count)) {
            return null;
        }

        $lastPoint = 0;
        $matchLen = count($matches[0]);
        [0 => $all, 1 => $word, 2 => $symbol, 3 => $number, 4 => $lf] = $matches;
        for ($i = 0; $i < $matchLen; $i++) {
            [$str, $point] = $all[$i];
            if (-1 === $point) {
                throw new \UnexpectedValueException('无法处理：超出范围 -1');
            }
            $strLen = strlen($str);
            if ($point > $lastPoint) {
                // 没有捕获
                $unCaptured = substr($text, $lastPoint, $point - $lastPoint);
                if ('' !== trim($unCaptured)) {
                    $unCaptured  = mb_check_encoding($unCaptured, 'utf8') ? $unCaptured : ('0x' . bin2hex($unCaptured));
                    throw new \UnexpectedValueException("无法处理：未知捕获 $point:({$unCaptured})");
                }
            }

            $lastPoint = $point + $strLen;
            if (-1 !== $word[$i][1]) {
                yield [
                    'type' => self::TYPE_WORD,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $symbol[$i][1]) {
                yield [
                    'type' => self::TYPE_SYMBOL,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $number[$i][1]) {
                yield [
                    'type' => self::TYPE_NUMBER,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $lf[$i][1]) {
                yield [
                    'type' => self::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } else {
                throw new \UnexpectedValueException("无法处理：未知分支 $point:({$str})");
            }
        }
        if (strlen($text) > $lastPoint) {
            $str = substr($text, $lastPoint);
            if (self::SYMBOL_LF === $str) {
                yield [
                    'type' => self::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } else {
                yield from self::wordTypeGuess([$str]);
            }
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

            if (self::TYPE_LF === $type
                || self::TYPE_NUMBER === $type
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
            } elseif (self::TYPE_SYMBOL === $word['type'] && in_array($wt, self::SYMBOL_CUT)) {
                $text .= $wt . ' ';
            } elseif (self::TYPE_SYMBOL === $word['type'] && in_array($wt, self::SYMBOL_BRACKETS_A)) {
                $text .= ' ' . $wt;
            } elseif (self::TYPE_SYMBOL === $word['type'] && in_array($wt, self::SYMBOL_BRACKETS_B)) {
                $text .=  $wt . ' ';
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
            if ($isWord) {
                $type = TextWordService::TYPE_WORD;
            } elseif (preg_match('/^[\p{N}.]+$/u', $item) > 0) {
                $type = TextWordService::TYPE_NUMBER;
            } else {
                $type = TextWordService::TYPE_SYMBOL;
            }
            yield [
                'type' => $type,
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
