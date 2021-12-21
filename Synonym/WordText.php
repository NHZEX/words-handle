<?php

namespace app\Service\TextWord\Synonym;

class WordText implements \Stringable, \JsonSerializable
{
    protected bool $relevant;

    protected string $text;

    protected ?string $partOfSpeech = null;

    public array $attr = [];

    public function __construct(string $text, bool $relevant = true, ?string $partOfSpeech = null)
    {
        $this->text         = $text;
        $this->relevant     = $relevant;
        $this->partOfSpeech = $partOfSpeech;
    }

    public function isRelevant(): bool
    {
        return $this->relevant;
    }

    public function text(): string
    {
        return $this->text;
    }

    public function __toString(): string
    {
        return $this->text;
    }

    public function jsonSerialize(): array
    {
        return [
            'text'         => $this->text,
            'relevant'     => $this->relevant,
            'partOfSpeech' => $this->partOfSpeech,
            'attr'         => $this->attr,
        ];
    }
}
