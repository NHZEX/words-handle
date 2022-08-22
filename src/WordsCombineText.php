<?php

namespace Zxin\TextWord;

use Zxin\TextWord\Symbol\SD_ISO3166;
use function array_flip;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function join;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function ucfirst;

final class WordsCombineText
{
    /** @var array<int, TextNode> */
    private array $words;

    private bool $forceFirstLetterUpper = false;

    protected bool $quotationBegin = false;

    protected bool $iso3166Alpha2ToUpper = false;

    protected bool $filterSymbol = false;

    protected ?string $formatStyle = null;

    public static ?array $dictForceUpper       = null;
    public static ?array $dictForceLower       = null;
    public static ?array $dictFirstLetterUpper = null;

    public const STYLE_FORMAT_FEATURE = 'product_feature';

    protected function __construct(array $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    public static function makeFromArray(array $words): WordsCombineText
    {
        $nodes = [];
        foreach ($words as $word) {
            $nodes[] = new TextNode($word['type'], $word['text'], $word['stat']);
        }
        return new self($nodes);
    }

    public static function makeFromNodes(array $nodes): WordsCombineText
    {
        return new self($nodes);
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
     * @param bool $filterSymbol
     */
    public function setFilterSymbol(bool $filterSymbol): void
    {
        $this->filterSymbol = $filterSymbol;
    }

    /**
     * @param string|null $style
     */
    public function setFormatStyle(?string $style): void
    {
        $this->formatStyle = $style;
    }

    /**
     * @param array<int, TextNode> $items
     * @return array<int, TextNode>
     */
    protected function blockRewrite(array $items): array
    {
        $len    = count($items);
        /** @var array<int, TextNode> $blocks */
        $blocks = [];
        for ($i = 0; $i < $len; $i++) {
            $word = $items[$i];
            if ($word->isNumber()) {
                $nextText = $items[$i + 1]->text ?? null;
                if (null === $nextText) {
                    goto END;
                } elseif (
                    3 === strlen($nextText)
                    && 'x' === $nextText[-1]
                    && $_symbol = SymbolDefinition::findSymbol(substr($nextText, 0, -1))
                ) {
                    // 数字后面跟着的符号
                    $blocks[]     = $word->cloneNode($word->text . ' ' . $_symbol);
                    $blocks[]     = TextNode::makeWord('x');
                    $i            += 1;
                } elseif ($_symbol = SymbolDefinition::findSymbol($nextText)) {
                    // 数字后面跟着的符号
                    $blocks[]     = $word->cloneNode($word->text . ' ' . $_symbol);
                    $i            += 1;
                } elseif ('°' === $nextText && isset($items[$i + 2]) && in_array($items[$i + 2]->toLower(), ['c', 'f'])) {
                    // 温度
                    $blocks[]     = $word->cloneNode($word->text . '°' . $items[$i + 2]->toUpper());
                    $i            += 2;
                } else {
                    goto END;
                }
            } elseif (
                isset($items[$i + 1])
                && isset($items[$i + 2])
                && $word->isWord()
                && '-' === $items[$i + 1]->text
                && $items[$i + 2]->isWord()
            ) {
                // 连接不需要空格
                $word = $word->cloneNode($word->text . '-' . $items[$i + 2]->text);
                $i            += 2;
                while (
                    isset($items[$i + 1])
                    && '-' === $items[$i + 1]->text
                    && $items[$i + 2]->isWord()
                ) {
                    $word = $word->cloneNode($word->text . '-' . $items[$i + 2]->text);
                    $i   += 2;
                }
                $blocks[]     = $word;
            } else {
                END:
                $blocks[] = $word;
            }
        }
        return $blocks;
    }

    public function build(): array
    {
        $items                = $this->preProcess($this->words);
        $this->quotationBegin = false;
        $len                  = count($items);

        // array{type: string, text: string, stat: int|null}

        /** @var TextNode[] $output */
        $output = [];

        for ($i = 0; $i < $len; $i++) {
            $node = $items[$i];

            // 块重写
            if (
                $node->isWord()
                && $sentence = (TextConstants::BLOCK_FORCE_LOWER[$node->toLower()] ?? null)
            ) {
                $newIndex = 0;
                if ($_text = $this->blockRewriteAnalyze($items, $sentence, $i, $newIndex)) {
                    $output[] = TextNode::makeWord($_text, $node->stat);
                    $output[] = TextNode::makeSpace();
                    $i    += $newIndex;
                    // 重新定位
                    $node = $items[$i];
                }
            }

            if ($node->isWord()) {
                $newIndex = 0;
                if ($_text = $this->blockAnalyzeISO3166($items, $i, $newIndex)) {
                    $output[] = TextNode::makeWord($_text, $node->stat);
                    $i    += $newIndex;
                    // 重新定位
                    $node = $items[$i];
                    // 简易处理符号问题
                    if ($node->isSymbol()) {
                        $output[] = TextNode::makeSpace();
                    }
                }
            }

            // 词重写1
            if ($this->forceFirstLetterUpper && $node->isWord()) {
                $node->writeText($node->toFirstCharUpper());
            }
            if (
                $node->isWord()
                && isset(self::$dictFirstLetterUpper[$node->toLower()])
            ) {
                $node = $node->cloneNode($node->toFirstCharUpper());
            } elseif (
                $node->isWord()
                && isset(self::$dictForceUpper[$node->toLower()])
            ) {
                $node = $node->cloneNode($node->toUpper());
            } elseif (
                $this->iso3166Alpha2ToUpper
                && $node->isWord()
                && 2 === strlen($node->text)
                && isset(SD_ISO3166::ALPHA2[$node->toLower()])
            ) {
                $node = $node->cloneNode($node->toUpper());
            } elseif (
                $node->isWord()
                && isset(self::$dictForceLower[$_lower = $node->toLower()])
            ) {
                // 强制小写词
                $node = $node->cloneNode($_lower);
            }
            if (
                0 !== $i
                && $node->isWord()
                && $items[$i - 1]->isSymbol()
                && ($items[$i - 1]->isEqual(':') || $items[$i - 1]->isEqual('.'))
                && !$node->isEqual($node->toUpper())
            ) {
                // 冒号、句号后跟着的字母大写
                $node = $node->cloneNode($node->toFirstCharUpper());
            } elseif (
                0 !== $i
                && $node->isWord()
                && $items[$i - 1]->isWrap()
                && !$node->isEqual($node->toUpper())
            ) {
                // 换行后跟着的字母大写
                $node = $node->cloneNode($node->toFirstCharUpper());
            } elseif (
                $this->quotationBegin
                && $node->isWord()
                && $items[$i - 1]->isEqual(TextConstants::SYMBOL_QUOTATION)
                && !$node->isEqual($node->toUpper())
            ) {
                // 被引用的句子第一个词首字母要大写
                $node = $node->cloneNode($node->toFirstCharUpper());
            }

            // 词重写2
            if (
                $node->isWord()
                && $node->len() > 1
                && ($_str = substr($node->text, 1))
                && !$node->isEqual($node->toUpper())
                && $_str !== ($_lower = strtolower($_str))
            ) {
                $node = $node->cloneNode($node->text[0] . $_lower);
            }

            // 上下文分析结合
            if ($i === $len - 1) {
                $output[] = clone $node;
            } elseif ($node->isWrap() || $items[$i + 1]->isWrap()) {
                // 换行后面不需要空格
                $output[] = clone $node;
            } elseif ($node->isEqual('.') && $items[$i + 1]->isEqual('.') && $items[$i + 2]->isEqual('.')) {
                // 解决：省略号
                $output[] = TextNode::makeWord('...');
                $output[] = TextNode::makeSpace();
                $i    += 2;
            } elseif (
                $node->isWord()
                && $items[$i + 1]->isEqual(TextConstants::SYMBOL_APOSTROPHE)
                && $items[$i + 2]->isWord()
            ) {
                // 撇号连接不需要空格 todo 可能需要字典
                $output[] = TextNode::makeWord($node->text . TextConstants::SYMBOL_APOSTROPHE . $items[$i + 2]->toLower());
                $output[] = TextNode::makeSpace();
                $i    += 2;
            } elseif ($node->isWord()
                && TextConstants::SYMBOL_APOSTROPHE === $node->text[-1]
                && $items[$i + 1]->isWord()
            ) {
                // 撇号连接不需要空格 todo 可能需要字典
                $output[] = TextNode::makeWord($node->text . $items[$i + 1]->toLower());
                $output[] = TextNode::makeSpace();
                $i    += 1;
            } elseif ($node->isSymbol() && null !== ($filling = $this->symbolSpaceAnalyze($items, $i, $node))) {
                if ('L' === $filling) {
                    $output[] = TextNode::makeSpace();
                    $output[] = clone $node;
                } elseif ('R' === $filling) {
                    $output[] = clone $node;
                    $output[] = TextNode::makeSpace();
                } else {
                    $output[] = clone $node;
                }
            } elseif ($node->isNumber() && $_text = $this->blockAnalyzeNumber($items, $i, $_next)) {
                // 数字开头分析
                $i    += $_next;
                $output[] = TextNode::makeNumber($_text);
                if (isset($items[$i + 1]) && !($items[$i + 1]->isSymbol() || $items[$i + 1]->isWrap())) {
                    $output[] = TextNode::makeSpace();
                }
            } elseif (
                $node->isSymbol()
                && $node->isEqual('-')
                && isset($items[$i - 1])
                && $items[$i - 1]->isWord()
                && isset($items[$i + 1])
                && ($items[$i + 1]->isWord() || $items[$i + 1]->isNumber())
            ) {
                // 解决：引号、连接符
                $output[] = clone $node;
            } elseif ($items[$i + 1]->isSymbol()) {
                // 解决：引号、连接符
                $output[] = clone $node;
            } else {
                $output[] = clone $node;
                $output[] = TextNode::makeSpace();
            }
        }

        return $output;
    }

    /**
     * @param array<TextNode> $nodes
     */
    public function toString(array $nodes): string
    {
        $text = '';
        foreach ($nodes as $i => $node) {
            if (0 === $i
                && $node->isWord()
                && !$node->isEqual($node->toUpper())
            ) {
                // 句首大写
                $node->writeText($node->toFirstCharUpper());
            }
            $text .= $node->text;
        }

        return $this->postProcess($text);
    }

    public function buildString(): string
    {
        return $this->toString($this->build());
    }

    /**
     * @param array<TextNode> $items
     * @param string[][] $sentence
     */
    protected function blockRewriteAnalyze(array $items, array $sentence, int $i, int &$next): ?string
    {
        if (count($sentence) === 0) {
            return null;
        }
        foreach ($sentence as $part) {
            if (empty($part)) {
                continue;
            }
            $isOk = true;
            foreach ($part as $_si => $val) {
                if (
                    !isset($items[$i + $_si])
                    || !$items[$i + $_si]->isWord()
                    || !$val !== $items[$i + $_si]->toLower()
                ) {
                    $isOk = false;
                    break;
                }
            }
            if ($isOk) {
                $next = count($part);
                return join(' ', $part);
            }
        }
        return null;
    }

    /**
     * @param array<int, TextNode> $items
     * @param int   $i
     * @param int   $next
     * @return string|null
     */
    protected function blockAnalyzeISO3166(array $items, int $i, int &$next): ?string
    {
        $word = $items[$i];
        $wt   = $word->text;

        $sentence = SD_ISO3166::NAME_DICT[strtolower(substr($wt, 0, 2))] ?? null;
        if (empty($sentence)) {
            return null;
        }
        $items = $this->words;
        foreach ($sentence as $country) {
            foreach ($country as $_si => $val) {
                if (strtolower($val) !== strtolower($items[$i + $_si]->text ?? '')) {
                    continue 2;
                }
            }
            $next = count($country);
            return join(' ', $country);
        }
        return null;
    }

    /**
     * @param array<TextNode> $items
     */
    protected function blockAnalyzeNumber(array $items, int $i, ?int &$next): ?string
    {
        $item  = $items[$i];
        if (
            count($items) - 1 >= $i + 3
            && strlen($item->text) <= 2
            && ctype_digit($item->text)
            && ':' === $items[$i + 1]->text
            && strlen($items[$i + 2]->text) <= 2
            && ctype_digit($items[$i + 2]->text)
            && in_array($items[$i + 3]->toLower(), ['am', 'pm'])
        ) {
            // 时间字符串带后缀
            $next = 3;
            return "{$item->text}:{$items[$i + 2]->text}" . $items[$i + 3]->toUpper();
        } elseif (
            count($items) - 1 >= $i + 2
            && strlen($item->text) <= 2
            && ctype_digit($item->text)
            && ':' === $items[$i + 1]->text
            && strlen($items[$i + 2]->text) <= 2
            && ctype_digit($items[$i + 2]->text)
        ) {
            // 时间字符串
            $next = 2;
            return "{$item->text}:{$items[$i + 2]->text}";
        } elseif (
            $item->isNumber()
            && ($_op = SymbolDefinition::isNumberOperator($items[$i + 1]->text))
            && $items[$i + 2]->isNumber()
        ) {
            // 运算符
            $output = $item->text . " {$_op} " . $items[$i + 2]->text;
            $next   = 2;
            while (
                isset($items[$i + $next + 1])
                && isset($items[$i + $next + 2])
                && ($_op = SymbolDefinition::isNumberOperator($items[$i + $next + 1]->text))
                && $items[$i + $next + 2]->isNumber()
            ) {
                $output .= " {$_op} " . $items[$i + $next + 2]->text;
                $next   += 2;
            }
            return $output;
        } else {
            return null;
        }
    }

    /**
     * @param array<int, TextNode>    $items
     * @param int      $i
     * @param TextNode $word
     * @return string|null
     */
    protected function symbolSpaceAnalyze(array $items, int $i, TextNode $word): ?string
    {
        $text = $word->text;
        if (in_array($text, TextConstants::SYMBOL_LINK)) {
            return '';
        } elseif (in_array($text, TextConstants::SYMBOL_CUT)) {
            return 'R';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_A)) {
            if (isset($items[$i - 1]) && $items[$i - 1]->isSymbol()) {
                return '';
            }
            return 'L';
        } elseif (in_array($text, TextConstants::SYMBOL_BRACKETS_B)) {
            return 'R';
        } else {
            return null;
        }
    }

    /**
     * @param array<int, TextNode> $nodes
     * @return array<int, TextNode>
     */
    protected function preProcess(array $nodes): array
    {
        $items = [];
        foreach ($nodes as $word) {
            if ($this->filterSymbol && $word->isSymbol()) {
                continue;
            }

            $items[] = $word;
        }

        return $this->blockRewrite($items);
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
                        $word = implode('-', array_map(fn ($t) => ucfirst(strtolower($t)), explode('-', $word)));
                        $headArr[] = $word;
                    }

                    return implode(' ', $headArr) . $end;
                }
        }
        return $text;
    }
}
