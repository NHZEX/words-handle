<?php

namespace Zxin\TextWord;

use JsonSerializable;
use function strlen;
use function strtolower;
use function strtoupper;

class TextNode implements JsonSerializable
{
    /**
     * @readonly
     * @psalm-readonly
     * @var string
     */
    public string $text;

    /**
     * @readonly
     * @psalm-readonly
     * @var string
     */
    public string $type;

    /**
     * @readonly
     * @psalm-readonly
     * @var int|null
     */
    public ?int   $stat;

    private ?string $_lower = null;
    private ?string $_upper = null;
    private ?string $_ucFirst = null;
    private int     $length;

    public function __construct(string $type, string $text, ?int $stat = null)
    {
        // 长度可能需要多字节支持

        $this->type = $type;
        $this->text = $text;
        $this->stat = $stat;

        $this->length = strlen($text);
    }

    public static function makeWord(string $text, ?int $stat = null): TextNode
    {
        return new self(TextConstants::TYPE_WORD, $text, $stat);
    }

    public static function makeNumber(string $text): TextNode
    {
        return new self(TextConstants::TYPE_NUMBER, $text);
    }

    public static function makeSymbol(string $text): TextNode
    {
        return new self(TextConstants::TYPE_SYMBOL, $text);
    }

    public static function makeSpace(): TextNode
    {
        return new self(TextConstants::TYPE_SPACE, ' ');
    }

    public static function makeWrap(): TextNode
    {
        return new self(TextConstants::TYPE_LF, "\n");
    }

    public function isWord(): bool
    {
        return TextConstants::TYPE_WORD === $this->type;
    }

    public function isOneChar(): bool
    {
        return 1 === $this->length;
    }

    public function isNumber(): bool
    {
        return TextConstants::TYPE_NUMBER === $this->type;
    }

    public function isSymbol(): bool
    {
        return TextConstants::TYPE_SYMBOL === $this->type;
    }

    public function isWrap(): bool
    {
        return TextConstants::TYPE_LF === $this->type;
    }

    public function isSpace(): bool
    {
        return TextConstants::TYPE_SPACE === $this->type;
    }

    public function len(): int
    {
        return $this->length;
    }

    public function toLower(): string
    {
        if (isset($this->_lower)) {
            return $this->_lower;
        }
        return $this->_lower = strtolower($this->text);
    }

    public function toUpper(): string
    {
        if (isset($this->_upper)) {
            return $this->_upper;
        }
        return $this->_upper = strtoupper($this->text);
    }

    public function toFirstCharUpper(): string
    {
        if (isset($this->_ucFirst)) {
            return $this->_ucFirst;
        }
        return $this->_ucFirst = ucfirst($this->toLower());
    }

    public function isEqual(string $text): bool
    {
        return $text === $this->text;
    }

    public function writeText(string $text)
    {
        $this->text = $text;
    }

    public function cloneNode(string $text): self
    {
        $that = clone $this;
        $that->text = $text;
        return $that;
    }

    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'stat' => $this->stat,
            'text' => $this->text,
        ];
    }
}
