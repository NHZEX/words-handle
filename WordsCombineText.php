<?php

namespace app\Service\TextWord;

use function in_array;
use function is_numeric;
use function ucfirst;

final class WordsCombineText
{
    private array $words;

    private bool $forceFirstLetterUpper = false;

    protected bool $_cxtCombineQuotationHead = false;

    protected bool $_cxtCombineSingleQuotationHead = false;

    public function __construct(array $words)
    {
        $this->words = $words;
    }

    public function setForceFirstLetterUpper(bool $forceFirstLetterUpper): self
    {
        $this->forceFirstLetterUpper = $forceFirstLetterUpper;

        return $this;
    }

    public function build(): string
    {
        $this->_cxtCombineQuotationHead = false;
        $this->_cxtCombineSingleQuotationHead = false;
        $text = '';
        $items = $this->words;
        $len  = count($items);
        foreach ($items as $i => $word) {
            /** @var string $wt */
            ['text' => $wt, 'type' => $type] = $word;
            if ($this->forceFirstLetterUpper && TextConstants::TYPE_WORD === $type) {
                $wt = ucfirst($wt);
            } elseif (
                0 !== $i
                && TextConstants::TYPE_WORD === $type
                && TextConstants::TYPE_SYMBOL === $items[$i - 1]['type']
                && ':' === $items[$i - 1]['text']
            ) {
                // 冒号后跟着的字母大写
                $wt = ucfirst($wt);
            }
            if ($i === $len - 1) {
                $text .= $wt;
            } elseif (TextConstants::TYPE_LF === $type || TextConstants::TYPE_LF === $items[$i + 1]['type']) {
                // 换行后面不需要空格
                $text .= $wt;
            } elseif (TextConstants::TYPE_SYMBOL === $type && null !== ($filling = $this->symbolSpaceAnalyze($wt))) {
                if ('L' === $filling) {
                    $text .= ' ' . $wt;
                } elseif ('R' === $filling) {
                    $text .= $wt . ' ';
                } else {
                    $text .= $wt;
                }
            } elseif (TextConstants::TYPE_SYMBOL === $items[$i + 1]['type']) {
                // 解决：引号、连接符
                $text .= $wt;
            } elseif (is_numeric($wt) && SymbolDefinition::isSymbol($items[$i + 1]['text'])) {
                $text .= $wt;
            } else {
                $text .= $wt . ' ';
            }
        }
        return $text;
    }

    protected function symbolSpaceAnalyze(string $text): ?string
    {
        if (in_array($text, TextConstants::SYMBOL_LINK)) {
            return '';
        } elseif (in_array($text, TextConstants::SYMBOL_CUT)) {
            return 'R';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_A)) {
            return 'L';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_B)) {
            return 'R';
        } elseif ($text === TextConstants::SYMBOL_QUOTE) {
            if ($this->_cxtCombineQuotationHead) {
                $this->_cxtCombineQuotationHead = false;
                return 'R';
            } else {
                $this->_cxtCombineQuotationHead = true;
                return 'L';
            }
        } elseif ($text === TextConstants::SYMBOL_SINGLE_QUOTATION) {
            if ($this->_cxtCombineSingleQuotationHead) {
                $this->_cxtCombineSingleQuotationHead = false;
                return 'R';
            } else {
                $this->_cxtCombineSingleQuotationHead = true;
                return 'L';
            }
        } else {
            return null;
        }
    }
}
