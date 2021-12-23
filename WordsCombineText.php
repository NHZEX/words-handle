<?php

namespace app\Service\TextWord;

use function array_flip;
use function in_array;
use function is_numeric;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function ucfirst;

final class WordsCombineText
{
    private array $words;

    private bool $forceFirstLetterUpper = false;

    protected bool $quotationBegin = false;

    protected bool $_cxtCombineSingleQuotationHead = false;

    static ?array $dictForceUpper = null;
    static ?array $dictForceLower = null;
    static ?array $dictFirstLetterUpper = null;

    public function __construct(array $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    protected function initDict()
    {
        self::$dictForceUpper = array_flip(TextConstants::FORCE_UPPER);
        self::$dictForceLower = array_flip(TextConstants::FORCE_LOWER);
        self::$dictFirstLetterUpper = array_flip([
            ...TextConstants::MONTH,
            ...TextConstants::WEEK,
        ]);
    }

    public function setForceFirstLetterUpper(bool $forceFirstLetterUpper): self
    {
        $this->forceFirstLetterUpper = $forceFirstLetterUpper;

        return $this;
    }

    public function build(): string
    {
        $this->quotationBegin                 = false;
        $this->_cxtCombineSingleQuotationHead = false;
        $text = '';
        $items = $this->words;
        $len  = count($items);
        for ($i = 0; $i < $len; $i++) {
            $word = $this->words[$i];
            /** @var string $wt */
            ['text' => $wt, 'type' => $type] = $word;

            // 词重写
            if (TextConstants::TYPE_WORD === $type
                && isset(self::$dictForceLower[$_lower = strtolower($wt)])
            ) {
                // 优先级低，强制小写词
                $wt = $_lower;
            }

            // 词重写1
            if ($this->forceFirstLetterUpper && TextConstants::TYPE_WORD === $type) {
                $wt = ucfirst($wt);
            } elseif (
                TextConstants::TYPE_WORD === $type
                && isset(self::$dictForceUpper[strtolower($wt)])
            ) {
                $wt = strtoupper($wt);
            } elseif (
                TextConstants::TYPE_WORD === $type
                && isset(self::$dictFirstLetterUpper[strtolower($wt)])
            ) {
                $wt = ucfirst($wt);
            } elseif (
                0 !== $i
                && TextConstants::TYPE_WORD === $type
                && TextConstants::TYPE_SYMBOL === $items[$i - 1]['type']
                && (':' === $items[$i - 1]['text'] || '.' === $items[$i - 1]['text'])
            ) {
                // 冒号、句号后跟着的字母大写
                $wt = ucfirst($wt);
            } elseif (
                0 !== $i
                && TextConstants::TYPE_WORD === $type
                && TextConstants::TYPE_LF === $items[$i - 1]['type']
            ) {
                // 换行后跟着的字母大写
                $wt = ucfirst($wt);
            } elseif (
                $this->quotationBegin
                && TextConstants::TYPE_WORD === $type
                && TextConstants::SYMBOL_QUOTATION === $items[$i - 1]['text']
            ) {
                // 被引用的句子第一个词首字母要大写
                $wt = ucfirst($wt);
            }

            // 词重写2
            if (
                TextConstants::TYPE_WORD === $type
                && strlen($wt) > 1
                && ($_str = substr($wt, 1))
                && $_str !== strtoupper($_str)
                && $_str !== ($_lower = strtolower($_str))
            ) {
                $wt = $wt[0] . $_lower;
            }

            // 上下文分析结合
            if ($i === $len - 1) {
                $text .= $wt;
            } elseif (TextConstants::TYPE_LF === $type || TextConstants::TYPE_LF === $items[$i + 1]['type']) {
                // 换行后面不需要空格
                $text .= $wt;
            } elseif (
                0 !== $i
                && TextConstants::TYPE_NUMBER === $items[$i]['type']
                && ('x' === strtolower($items[$i + 1]['text']) || '*' === $items[$i + 1]['text'])
                && TextConstants::TYPE_NUMBER === $items[$i + 2]['type']
            ) {
                $text .= $wt . 'x' . $items[$i + 2]['text'];
                $i += 2;
            } elseif (TextConstants::TYPE_SYMBOL === $type && null !== ($filling = $this->symbolSpaceAnalyze($i, $word))) {
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
                // 数字后面跟着的符号不需要空格
                $text .= $wt;
            } else {
                $text .= $wt . ' ';
            }
        }
        return $text;
    }

    protected function symbolSpaceAnalyze(int $i, array $word): ?string
    {
        ['text' => $text] = $word;
        if (in_array($text, TextConstants::SYMBOL_LINK)) {
            return '';
        } elseif (in_array($text, TextConstants::SYMBOL_CUT)) {
            return 'R';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_A)) {
            return 'L';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_B)) {
            return 'R';
        } elseif ($text === TextConstants::SYMBOL_QUOTATION) {
            if ($this->quotationBegin) {
                $this->quotationBegin = false;
                return 'R';
            } else {
                $this->quotationBegin = true;
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
