<?php

namespace app\Service\TextWord;

use IteratorAggregate;
use UnexpectedValueException;
use function bin2hex;
use function count;
use function in_array;
use function log_warning;
use function preg_match_all;
use function preg_split;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function trim;

/**
 * @implements IteratorAggregate<int, TextNode>
 */
class TextSegment implements IteratorAggregate
{
    private string $text;

    protected bool $ignoreSpace = true;

    protected bool $ignoreUTF8Character = false;

    protected bool $ignore4CharError = false;

    protected array $ignoreChar = [
//        "\xef\xb8\x8f", // 造成问题 "❤️"
    ];

    public function __construct(string $text)
    {
        $this->text = str_replace("\r\n", '\n', $text);
    }

    public static function input(string $text): TextSegment
    {
        return new self($text);
    }

    public function setIgnoreInvalidCharacter(bool $ignore): static
    {
        $this->ignoreUTF8Character = $ignore;
        return $this;
    }

    public function setIgnore4CharError(bool $ignore4CharError): static
    {
        $this->ignore4CharError = $ignore4CharError;
        return $this;
    }

    /**
     * @return \Iterator<int, TextNode>|iterable<int, TextNode>
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \Iterator
    {
        return $this->slice();
    }

    public function toArray(): array
    {
        $words = [];
        foreach ($this->slice() as $item) {
            $words[] = $item;
        }

        return $words;
    }

    /**
     * @return \Generator|iterable<int, TextNode>
     */
    public function slice(): \Generator
    {
        if ($this->ignoreUTF8Character) {
            $result = mb_convert_encoding($this->text, 'UTF-8', 'UTF-8');
            if (false === $result) {
                throw new UnexpectedValueException("无法编解码的无效字符串（non-utf8 characters）");
            }
            $this->text = $result;
        }

        $section = preg_split('/(\s)/mu', $this->text, 0, PREG_SPLIT_OFFSET_CAPTURE | PREG_SPLIT_DELIM_CAPTURE);

        foreach ($section as $part) {
            [$w, $pos] = $part;

            if ($w === "\n") {
                yield TextNode::makeWrap();
            } else if (!$this->ignoreSpace && ($w === ' ' || $w === "\t")) {
                yield TextNode::makeSpace();
            } else if ('' === trim($w)) {
                continue;
            } else {
                yield from $this->optimize($this->tokenizer($w, $pos));
            }
        }
    }

    /**
     * @param string $section
     * @return \Generator|iterable<int, TextNode>
     */
    protected function tokenizer(string $section, int $pos): \Generator
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
                if ('' !== trim($unCaptured) && !in_array($unCaptured, $this->ignoreChar)) {
                    $unHex = '0x' . bin2hex($unCaptured);
                    $unCaptured  = mb_check_encoding($unCaptured, 'utf8') ? $unCaptured : $unHex;
                    $errMessage = sprintf('无法处理：未知捕获 %s:(%s)[%s], location: (%s)', $point, $unCaptured, $unHex, substr($section, 0, 16));
                    if ($this->ignore4CharError && strlen($unCaptured) <= 4) {
                        log_warning($errMessage);
                    } else {
                        throw new \UnexpectedValueException($errMessage);
                    }
                }
            }

            $lastPoint = $point + $strLen;
            if (-1 !== $symbol[$i][1]) {
                yield TextNode::makeSymbol($str);
            } elseif (-1 !== $word[$i][1]) {
                yield TextNode::makeWord($str);
            } elseif (-1 !== $number[$i][1]) {
                yield TextNode::makeNumber($str);
            } else {
                throw new \UnexpectedValueException("无法处理：未知分支 $point:({$str})");
            }
        }
        if (strlen($section) > $lastPoint) {
            $str = substr($section, $lastPoint);
            if (TextConstants::SYMBOL_LF === $str) {
                yield TextNode::makeWrap();
            } elseif ('' !== trim($str)) {
                yield new TextNode(TextConstants::TYPE_UNKNOWN, $str);
            }
        }
    }

    /**
     * @param \Generator $it
     * @return \Generator|iterable<int, TextNode>
     */
    protected function optimize(\Generator $it): \Generator
    {
        /** @var TextNode[] $buffer */
        $buffer = [];
        while ($it->valid()) {
            /** @var TextNode $word */
            $word = $it->current();
            $it->next();

            if (
                // 分析连结单字母词
                ($word->isNumber() || $word->isWord()) && 1 === strlen($word->text)
                && null === SymbolDefinition::isNumberOperator($word->text)
            ) {
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

    /**
     * @param array<int, TextNode> $words
     * @return TextNode
     */
    public function wordConnect(array $words): TextNode
    {
        if (1 === count($words)) {
            return $words[0];
        }
        $text = '';
        foreach ($words as $item) {
            $text .= $item->text;
        }
        return TextNode::makeWord($text);
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
