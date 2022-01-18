<?php

namespace app\Service\TextWord;

use function strlen;
use function strtolower;
use function strtoupper;

class TextNode
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

    public function __construct(string $type, string $text, ?int $stat = null)
    {
        $this->type = $type;
        $this->text = $text;
        $this->stat = $stat;
    }

    public static function makeWord(string $text, ?int $stat = null): TextNode
    {
        return new self(TextConstants::TYPE_WORD, $text, $stat);
    }

    public static function makeNumber(string $text): TextNode
    {
        return new self(TextConstants::TYPE_NUMBER, $text);
    }

    public static function makeSpace(): TextNode
    {
        return new self(TextConstants::TYPE_SPACE, ' ');
    }

    public function isWord(): bool
    {
        return TextConstants::TYPE_WORD === $this->type;
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
        return strlen($this->text);
    }

    public function toLower(): string
    {
        return strtolower($this->text);
    }

    public function toUpper(): string
    {
        return strtoupper($this->text);
    }

    public function toFirstCharUpper(): string
    {
        return ucfirst($this->toLower());
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
}
