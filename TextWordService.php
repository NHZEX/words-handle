<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Dict\DictTypeFilter;
use Generator;
use Iterator;
use UnexpectedValueException;
use function bin2hex;
use function count;
use function htmlspecialchars_decode;
use function mb_check_encoding;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strip_tags;
use function strlen;
use function substr;
use function trim;
use function Zxin\Str\str_fullwidth_to_ascii;
use const ENT_HTML401;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

class TextWordService
{
    public function clean(string $text): string
    {
        $output = htmlspecialchars_decode($text);
        $output = strip_tags($output);
        $output = html_entity_decode($output, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401);
        /**$output = preg_replace('#<br\s?/?>#', "\n", $output);**/
        $output = Helper::filterSymbol(str_fullwidth_to_ascii($output));
        return preg_replace("/\n{2,}/u", "\n\n", $output);
    }

    public function slice(string $text): ?Generator
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
                throw new UnexpectedValueException('无法处理：超出范围 -1');
            }
            $strLen = strlen($str);
            if ($point > $lastPoint) {
                // 没有捕获
                $unCaptured = substr($text, $lastPoint, $point - $lastPoint);
                if ('' !== trim($unCaptured)) {
                    $unCaptured  = mb_check_encoding($unCaptured, 'utf8') ? $unCaptured : ('0x' . bin2hex($unCaptured));
                    throw new UnexpectedValueException("无法处理：未知捕获 $point:({$unCaptured})");
                }
            }

            $lastPoint = $point + $strLen;
            if (-1 !== $word[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_WORD,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $symbol[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_SYMBOL,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $number[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_NUMBER,
                    'stat' => null,
                    'text' => $str,
                ];
            } elseif (-1 !== $lf[$i][1]) {
                yield [
                    'type' => TextConstants::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } else {
                throw new UnexpectedValueException("无法处理：未知分支 $point:({$str})");
            }
        }
        if (strlen($text) > $lastPoint) {
            $str = substr($text, $lastPoint);
            if (TextConstants::SYMBOL_LF === $str) {
                yield [
                    'type' => TextConstants::TYPE_LF,
                    'stat' => null,
                    'text' => "\n",
                ];
            } elseif ('' !== trim($str)) {
                yield from self::wordTypeGuess([$str]);
            }
        }
    }

    /**
     * @deprecated
     */
    public function slice2(string $text): Iterator
    {
        return (new TextSegment($text))->getIterator();
    }

    public function filterOnlyInvalid(iterable $items): Generator
    {
        /** @var TextNode $item */
        foreach ($this->filter($items) as $item) {
            if (!$item->isWord()) {
                continue;
            }
            if ($item->stat > 0) {
                yield $item;
            }
        }
    }

    public function filter(iterable $items): Generator
    {
        yield from DictTypeFilter::input($items);
    }

    public function wordTypeGuess(iterable $items): Generator
    {
        foreach ($items as $item) {
            $isWord = preg_match('/^[\p{L}\p{Pd}\p{Zs}]+$/u', $item) > 0;
            if ($isWord) {
                $type = TextConstants::TYPE_WORD;
            } elseif (preg_match('/^[\p{N}.]+$/u', $item) > 0) {
                $type = TextConstants::TYPE_NUMBER;
            } else {
                $type = TextConstants::TYPE_SYMBOL;
            }
            yield new TextNode($type, $item);
        }
    }

    public function synonym(string $word): array
    {
        return SynonymService::instance()->queryAggregationWithCheckDict($word);
    }

    public function textCheckInvalid(string $text): array
    {
        $output = [];
        $it = $this->filterOnlyInvalid(TextSegment::input($this->clean($text))
            ->setIgnoreInvalidCharacter(true)
            ->setIgnore4CharError(true)
            ->getIterator());
        foreach ($it as $item) {
            $output[] = $item;
        }

        return $output;
    }
}
