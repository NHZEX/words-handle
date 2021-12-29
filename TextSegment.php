<?php

namespace app\Service\TextWord;

use function bin2hex;
use function count;
use function preg_match_all;
use function preg_split;
use function str_replace;
use function strlen;
use function substr;
use function trim;

class TextSegment
{
    private string $text;

    protected bool $ignoreSpace = true;

    public function __construct(string $text)
    {
        $this->text = str_replace("\r\n", '\n', $text);
    }

    public function slice(): \Generator
    {
        $section = preg_split('/(\s)/mu', $this->text, 0, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

        foreach ($section as $part) {
            [$w, $pos] = $part;

            if ($w === "\n") {
                yield [
                    'type' => TextConstants::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } else if (!$this->ignoreSpace && ($w === ' ' || $w === "\t")) {
                yield [
                    'type' => TextConstants::TYPE_SPACE,
                    'stat' => null,
                    'text' => ' ',
                ];
            } else if ('' === trim($w)) {
                continue;
            } else {
                yield from $this->optimize($this->tokenizer($w, $pos));
            }
        }
    }

    protected function tokenizer(string $section): \Generator
    {
        $count = preg_match_all(
            "/([\p{S}\p{P}])|(\p{L}+(?:[′'][A-Za-z]*)?)|(\p{N}+(?:\.\p{N}+)?)/u",
            $section,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if (empty($count)) {
            return null;
        }
        [0 => $all, 1 => $symbol, 2 => $word, 3 => $number] = $matches;
        $lastPoint = 0;
        $matchLen = count($matches[0]);
        for ($i = 0; $i < $matchLen; $i++) {
            [$str, $point] = $all[$i];
            if (-1 === $point) {
                throw new \UnexpectedValueException('无法处理：超出范围 -1');
            }
            $strLen = strlen($str);
            if ($point > $lastPoint) {
                // 没有捕获
                $unCaptured = substr($section, $lastPoint, $point - $lastPoint);
                if ('' !== trim($unCaptured)) {
                    $unCaptured  = mb_check_encoding($unCaptured, 'utf8') ? $unCaptured : ('0x' . bin2hex($unCaptured));
                    throw new \UnexpectedValueException("无法处理：未知捕获 $point:({$unCaptured})");
                }
            }

            $lastPoint = $point + $strLen;
            if (-1 !== $symbol[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_SYMBOL,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $word[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_WORD,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $number[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_NUMBER,
                    'stat' => null,
                    'text' => $str,
                ];
            } else {
                throw new \UnexpectedValueException("无法处理：未知分支 $point:({$str})");
            }
        }
        if (strlen($section) > $lastPoint) {
            $str = substr($section, $lastPoint);
            if (TextConstants::SYMBOL_LF === $str) {
                yield [
                    'type' => TextConstants::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } elseif ('' !== trim($str)) {
                yield [
                    'type' => TextConstants::TYPE_UNKNOWN,
                    'stat' => null,
                    'text' => $str,
                ];
            }
        }
    }

    protected function optimize(\Generator $it): \Generator
    {
        $buffer = [];
        while ($it->valid()) {
            $word = $it->current();
            $it->next();

            if (($word['type'] === TextConstants::TYPE_NUMBER || $word['type'] === TextConstants::TYPE_WORD) && 1 === strlen($word['text'])) {
                $buffer[] = $word;
            } else {
                if (count($buffer) > 0) {
                    yield $this->wordConnect($buffer);
                    $buffer = [];
                }
                yield $word;
            }
        }
        if (count($buffer) > 0) {
            yield $this->wordConnect($buffer);
        }
    }

    public function wordConnect(array $words): array
    {
        if (1 === count($words)) {
            return $words[0];
        }
        $text = '';
        foreach ($words as $item) {
            $text .= $item['text'];
        }
        return [
            'type' => TextConstants::TYPE_WORD,
            'stat' => null,
            'text' => $text,
        ];
    }

    public function __backup (string $section) {
        $count = preg_match_all(
            "/([\p{L}]+)|([\p{S}\p{P}])|(\p{N}+)/u",
            $section,
            $matches,
            PREG_OFFSET_CAPTURE
        );
        if (empty($count)) {
            return null;
        }
        [0 => $all, 1 => $word, 2 => $symbol, 3 => $number] = $matches;
        $lastPoint = 0;
        $matchLen = count($matches[0]);
        for ($i = 0; $i < $matchLen; $i++) {
            [$str, $point] = $all[$i];
            if (-1 === $point) {
                throw new \UnexpectedValueException('无法处理：超出范围 -1');
            }
            $strLen = strlen($str);
            if ($point > $lastPoint) {
                // 没有捕获
                $unCaptured = substr($section, $lastPoint, $point - $lastPoint);
                if ('' !== trim($unCaptured)) {
                    $unCaptured  = mb_check_encoding($unCaptured, 'utf8') ? $unCaptured : ('0x' . bin2hex($unCaptured));
                    throw new \UnexpectedValueException("无法处理：未知捕获 $point:({$unCaptured})");
                }
            }

            $lastPoint = $point + $strLen;
            if (-1 !== $symbol[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_SYMBOL,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $word[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_WORD,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $number[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_NUMBER,
                    'stat' => null,
                    'text' => $str,
                ];
            } else {
                throw new \UnexpectedValueException("无法处理：未知分支 $point:({$str})");
            }
        }
        if (strlen($section) > $lastPoint) {
            $str = substr($section, $lastPoint);
            if (TextConstants::SYMBOL_LF === $str) {
                yield [
                    'type' => TextConstants::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } elseif ('' !== trim($str)) {
                yield [
                    'type' => TextConstants::TYPE_UNKNOWN,
                    'stat' => null,
                    'text' => $str,
                ];
            }
        }
    }
}
