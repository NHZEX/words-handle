<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Symbol\SD_ISO3166;
use function array_flip;
use function count;
use function in_array;
use function is_numeric;
use function join;
use function strlen;
use function strtolower;
use function strtoupper;
use function substr;
use function ucfirst;

final class WordsCombineText
{
    /** @var array<int, array{type: string, text: string}> */
    private array $words;

    private bool $forceFirstLetterUpper = false;

    protected bool $quotationBegin = false;

    protected bool $iso3166Alpha2ToUpper = false;

    static ?array $dictForceUpper       = null;
    static ?array $dictForceLower       = null;
    static ?array $dictFirstLetterUpper = null;

    public function __construct(array $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    protected function initDict()
    {
        self::$dictForceUpper       = array_flip(TextConstants::FORCE_UPPER);
        self::$dictForceLower       = array_flip(TextConstants::FORCE_LOWER);
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
        $this->quotationBegin = false;
        $text                 = '';
        $items                = $this->words;
        $len                  = count($items);
        for ($i = 0; $i < $len; $i++) {
            /** @var array{type: string, text: string} $word */
            $word = $this->words[$i];
            /** @var string $wt */
            ['text' => $wt, 'type' => $type] = $word;

            // 块重写
            if (
                TextConstants::TYPE_WORD === $word['text']
                && $sentence = (TextConstants::BLOCK_FORCE_LOWER[strtolower($wt)] ?? null)
            ) {
                $newIndex = 0;
                if ($_text = $this->blockRewriteAnalyze($sentence, $i, $newIndex)) {
                    $text .= $_text . ' ';
                    $i    += $newIndex - 1;
                    // 重新定位
                    $word = $this->words[$i];
                    ['text' => $wt, 'type' => $type] = $word;
                }
            }

            if (TextConstants::TYPE_WORD === $type) {
                $newIndex = 0;
                if ($_text = $this->blockAnalyzeISO3166($i, $newIndex)) {
                    $text .= $_text;
                    $i    += $newIndex;
                    // 重新定位
                    $word = $this->words[$i];
                    ['text' => $wt, 'type' => $type] = $word;
                    // 简易处理符号问题
                    if (TextConstants::TYPE_SYMBOL !== $type) {
                        $text .= ' ';
                    }
                }
            }

            // 词重写1
            if ($this->forceFirstLetterUpper && TextConstants::TYPE_WORD === $type) {
                $wt = ucfirst($wt);
            }
            if (
                TextConstants::TYPE_WORD === $type
                && isset(self::$dictFirstLetterUpper[strtolower($wt)])
            ) {
                $wt = ucfirst($wt);
            } elseif (
                TextConstants::TYPE_WORD === $type
                && isset(self::$dictForceUpper[strtolower($wt)])
            ) {
                $wt = strtoupper($wt);
            } elseif (
                $this->iso3166Alpha2ToUpper
                && TextConstants::TYPE_WORD === $type
                && 2 === strlen($wt)
                && isset(SD_ISO3166::ALPHA2[strtolower($wt)])
            ) {
                $wt = strtoupper($wt);
            } elseif (TextConstants::TYPE_WORD === $type
                && isset(self::$dictForceLower[$_lower = strtolower($wt)])
            ) {
                // 强制小写词
                $wt = $_lower;
            }
            if (
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
            } elseif (TextConstants::TYPE_WORD === $type
                && TextConstants::SYMBOL_APOSTROPHE === $items[$i + 1]['text']
                && TextConstants::TYPE_WORD === $items[$i + 2]['type']
            ) {
                // 撇号连接不需要空格 todo 可能需要字典
                $text .= $wt . TextConstants::SYMBOL_APOSTROPHE . strtolower($items[$i + 2]['text']) . ' ';
                $i    += 2;
            } elseif (
                0 !== $i
                && TextConstants::TYPE_NUMBER === $items[$i]['type']
                && ('x' === strtolower($items[$i + 1]['text']) || '*' === $items[$i + 1]['text'])
                && TextConstants::TYPE_NUMBER === $items[$i + 2]['type']
            ) {
                $text .= $wt . 'x' . $items[$i + 2]['text'];
                $i    += 2;
                if ($_symbol = SymbolDefinition::findSymbol($items[$i + 1]['text'])) {
                    $text .= $_symbol . ' ';
                    $i    += 1;
                }
            } elseif (TextConstants::TYPE_SYMBOL === $type && null !== ($filling = $this->symbolSpaceAnalyze($i, $word))) {
                if ('L' === $filling) {
                    $text .= ' ' . $wt;
                } elseif ('R' === $filling) {
                    $text .= $wt . ' ';
                } else {
                    $text .= $wt;
                }
            } elseif (TextConstants::TYPE_NUMBER === $type && is_numeric($wt) && $_text = $this->blockAnalyzeNumber($i, $_next)) {
                // 数字分析
                $text .= $_text;
                $i    += $_next;
            } elseif (TextConstants::TYPE_SYMBOL === $items[$i + 1]['type']) {
                // 解决：引号、连接符
                $text .= $wt;
            } elseif (is_numeric($wt) && $_symbol = SymbolDefinition::findSymbol($items[$i + 1]['text'])) {
                // 数字后面跟着的符号
                $text .= $wt . $_symbol . ' ';
                $i    += 1;
            } else {
                $text .= $wt . ' ';
            }
        }
        return $text;
    }

    protected function blockRewriteAnalyze(array $sentence, int $i, int &$next): ?string
    {
        if (count($sentence) === 0) {
            return null;
        }
        $items = $this->words;
        foreach ($sentence as $item) {
            foreach ($item as $_si => $val) {
                if (
                    TextConstants::TYPE_WORD !== $items[$i + $_si]['type']
                    && $val !== strtolower($items[$i + $_si]['text'])
                ) {
                    continue 2;
                }
            }
            $next = count($item);
            return join(' ', $item);
        }
        return null;
    }

    protected function blockAnalyzeISO3166(int $i, int &$next): ?string
    {
        $word = $this->words[$i];
        $wt = $word['text'];

        $sentence = SD_ISO3166::NAME_DICT[strtolower(substr($wt, 0, 2))] ?? null;
        if (empty($sentence)) {
            return null;
        }
        $items = $this->words;
        foreach ($sentence as $country) {
            foreach ($country as $_si => $val) {
                if (strtolower($val) !== strtolower($items[$i + $_si]['text'])) {
                    continue 2;
                }
            }
            $next = count($country);
            return join(' ', $country);
        }
        return null;
    }

    protected function blockAnalyzeNumber(int $i, ?int &$next): ?string
    {
        ['text' => $wt, 'type' => $type] = $this->words[$i];
        if (
            count($this->words) - 1 >= $i + 3
            && strlen($wt) <= 2
            && ':' === $this->words[$i + 1]['text']
            && is_numeric($this->words[$i + 2]['text']) && strlen($this->words[$i + 2]['text']) <= 2
            && in_array(strtolower($this->words[$i + 3]['text']), ['am', 'pm'])
        ) {
            // 时间字符串
            $next = 3;
            return "{$wt}:{$this->words[$i + 2]['text']}" . strtoupper($this->words[$i + 3]['text']) . ' ';
        } else {
            return null;
        }
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
        } else {
            return null;
        }
    }
}
