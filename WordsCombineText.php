<?php

namespace app\Service\TextWord;

use app\Service\TextWord\Symbol\SD_ISO3166;
use function array_flip;
use function count;
use function explode;
use function implode;
use function in_array;
use function join;
use function strlen;
use function strpos;
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

    protected ?string $formatStyle = null;

    static ?array $dictForceUpper       = null;
    static ?array $dictForceLower       = null;
    static ?array $dictFirstLetterUpper = null;

    const STYLE_FORMAT_FEATURE = 'product_feature';

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

    /**
     * @param string|null $style
     */
    public function setFormatStyle(?string $style): void
    {
        $this->formatStyle = $style;
    }

    protected function blockRewrite()
    {

        $items  = $this->words;
        $len    = count($items);
        $blocks = [];
        for ($i = 0; $i < $len; $i++) {
            /** @var array{type: string, text: string} $word */
            $word = $items[$i];
            if (TextConstants::TYPE_NUMBER === $word['type']) {
                $nextText = $items[$i + 1]['text'] ?? null;
                if (null === $nextText) {
                    goto END;
                } elseif (
                    3 === strlen($nextText)
                    && 'x' === $nextText[-1]
                    && $_symbol = SymbolDefinition::findSymbol(substr($nextText, 0, -1))
                ) {
                    // 数字后面跟着的符号
                    $word['text'] = $word['text'] . $_symbol;
                    $blocks[]     = $word;
                    $blocks[]     = [
                        'type' => TextConstants::TYPE_WORD,
                        'text' => 'x',
                        'stat' => null,
                    ];
                    $i            += 1;
                } elseif ($_symbol = SymbolDefinition::findSymbol($nextText)) {
                    // 数字后面跟着的符号
                    $word['text'] = $word['text'] . $_symbol;
                    $blocks[]     = $word;
                    $i            += 1;
                } elseif ('°' === $nextText && in_array(strtolower($items[$i + 2]['text'] ?? ''), ['c', 'f'])) {
                    // 温度
                    $word['text'] = $word['text'] . '°' . strtoupper($items[$i + 2]['text']);
                    $blocks[]     = $word;
                    $i            += 2;
                } else {
                    goto END;
                }
            } else {
                END:
                $blocks[] = $word;
            }
        }
        $this->words = $blocks;
    }

    public function build(): string
    {
        $this->blockRewrite();
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
                $wt = ucfirst(strtolower($wt));
            }
            if (
                TextConstants::TYPE_WORD === $type
                && isset(self::$dictFirstLetterUpper[strtolower($wt)])
            ) {
                $wt = ucfirst(strtolower($wt));
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
                && strtoupper($wt) !== $wt
            ) {
                // 冒号、句号后跟着的字母大写
                $wt = ucfirst(strtolower($wt));
            } elseif (
                0 !== $i
                && TextConstants::TYPE_WORD === $type
                && TextConstants::TYPE_LF === $items[$i - 1]['type']
                && strtoupper($wt) !== $wt
            ) {
                // 换行后跟着的字母大写
                $wt = ucfirst($wt);
            } elseif (
                $this->quotationBegin
                && TextConstants::TYPE_WORD === $type
                && TextConstants::SYMBOL_QUOTATION === $items[$i - 1]['text']
                && strtoupper($wt) !== $wt
            ) {
                // 被引用的句子第一个词首字母要大写
                $wt = ucfirst(strtolower($wt));
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
            } elseif ('.' === $wt && '.' === $items[$i + 1]['text'] && '.' === $items[$i + 2]['text']) {
                // 解决：省略号
                $text .= '... ';
                $i    += 2;
            } elseif (TextConstants::TYPE_WORD === $type
                && TextConstants::SYMBOL_APOSTROPHE === $items[$i + 1]['text']
                && TextConstants::TYPE_WORD === $items[$i + 2]['type']
            ) {
                // 撇号连接不需要空格 todo 可能需要字典
                $text .= $wt . TextConstants::SYMBOL_APOSTROPHE . strtolower($items[$i + 2]['text']) . ' ';
                $i    += 2;
            } elseif (TextConstants::TYPE_WORD === $type
                && '-' === $items[$i + 1]['text']
                && TextConstants::TYPE_WORD === $items[$i + 2]['type']
            ) {
                // 连接不需要空格
                $text .= $wt . '-' . strtolower($items[$i + 2]['text']) . ' ';
                $i    += 2;
            } elseif (TextConstants::TYPE_SYMBOL === $type && null !== ($filling = $this->symbolSpaceAnalyze($i, $word))) {
                if ('L' === $filling) {
                    $text .= ' ' . $wt;
                } elseif ('R' === $filling) {
                    $text .= $wt . ' ';
                } else {
                    $text .= $wt;
                }
            } elseif (TextConstants::TYPE_NUMBER === $type && $_text = $this->blockAnalyzeNumber($i, $_next)) {
                // 数字开头分析
                $i    += $_next;
                if (isset($items[$i + 1])) {
                    $isSpace = in_array($items[$i + 1]['type'], [TextConstants::TYPE_SYMBOL, TextConstants::TYPE_LF]);
                    $text .= $_text . ($isSpace ? '' : ' ');
                } else {
                    $text .= $_text;
                }
            } elseif (TextConstants::TYPE_SYMBOL === $items[$i + 1]['type']) {
                // 解决：引号、连接符
                $text .= $wt;
            } else {
                $text .= $wt . ' ';
            }
        }
        return $this->postProcess($text);
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
        $wt   = $word['text'];

        $sentence = SD_ISO3166::NAME_DICT[strtolower(substr($wt, 0, 2))] ?? null;
        if (empty($sentence)) {
            return null;
        }
        $items = $this->words;
        foreach ($sentence as $country) {
            foreach ($country as $_si => $val) {
                if (strtolower($val) !== strtolower($items[$i + $_si]['text'] ?? '')) {
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

        $items = $this->words;
        $item  = $items[$i];
        ['text' => $wt, 'type' => $type] = $item;
        if (
            count($this->words) - 1 >= $i + 3
            && strlen($wt) <= 2
            && ':' === $this->words[$i + 1]['text']
            && strlen($this->words[$i + 2]['text']) <= 2
            && in_array(strtolower($this->words[$i + 3]['text']), ['am', 'pm'])
        ) {
            // 时间字符串
            $next = 3;
            return "{$wt}:{$this->words[$i + 2]['text']}" . strtoupper($this->words[$i + 3]['text']) . ' ';
        } elseif (
            TextConstants::TYPE_NUMBER === $items[$i]['type']
            && ($_op = SymbolDefinition::isNumberOperator($items[$i + 1]['text']))
            && TextConstants::TYPE_NUMBER === $items[$i + 2]['type']
        ) {
            // 运算符
            $_op    = '*' === $_op ? 'x' : $_op;
            $output = $wt . " {$_op} " . $items[$i + 2]['text'];
            $next   = 2;
            while (
                ($_op = SymbolDefinition::isNumberOperator($items[$i + $next + 1]['text'] ?? ''))
                && TextConstants::TYPE_NUMBER === $items[$i + $next + 2]['type']
            ) {
                $output .= " {$_op} " . $items[$i + $next + 2]['text'];
                $next   += 2;
            }
            return $output;
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
            if (TextConstants::TYPE_SYMBOL === ($this->words[$i - 1]['type'] ?? '')) {
                return '';
            }
            return 'L';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_B)) {
            return 'R';
        } else {
            return null;
        }
    }

    protected function postProcess(string $text): string
    {
        switch ($this->formatStyle) {
            case self::STYLE_FORMAT_FEATURE:
                $pos = strpos($text, ':');
                if (false === $pos || 0 === $pos) {
                    return $text;
                } else {
                    $head = substr($text, 0, $pos);
                    $end = substr($text, $pos);
                    $headArr = [];
                    foreach (explode(' ', $head) as $word) {
                        $headArr[] = ucfirst(strtolower($word));
                    }

                    return implode(' ', $headArr) . $end;
                }
        }
        return $text;
    }
}
