<?php

namespace Zxin\TextWord\Dict;

use Zxin\TextWord\TextConstants;
use Zxin\TextWord\TextNode;
use Zxin\TextWord\WordsCombineText;
use Iterator;
use IteratorAggregate;
use LogicException;
use Traversable;
use function array_flip;
use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function count;
use function implode;
use function is_array;
use function preg_replace;
use function strlen;
use function strpos;
use function strtolower;

abstract class DictFilterBase implements IteratorAggregate
{
    /**
     * @var Iterator<int, TextNode>
     */
    protected Iterator $words;

    /**
     * @var array<int, TextNode>
     */
    protected array $buffer = [];

    public static ?array $CUT_WORDS = null;

    /**
     * @param Iterator<int, TextNode> $words
     */
    public function __construct(Iterator $words)
    {
        $this->words = $words;

        $this->initDict();
    }

    /**
     * @param iterable<int, TextNode>|IteratorAggregate|DictFilterBase $data
     * @return $this
     */
    public static function input(iterable $data): DictFilterBase
    {
        if ($data instanceof IteratorAggregate) {
            $data = $data->getIterator();
        } elseif (is_array($data)) {
            $data = new \ArrayIterator($data);
        }
        return new static($data);
    }

    protected function initDict()
    {
        self::$CUT_WORDS = array_flip([...TextConstants::SYMBOL_CUT2, ...TextConstants::SYMBOL_SEG]);
    }

    abstract protected function exactQuery(string $queryText): ?array;

    abstract protected function prefixQuery(string $queryText): array;

    abstract protected function buildText(string $text, array $word): TextNode;

    /**
     * @param array<int, TextNode> $items
     * @return string
     */
    protected function joinWord(array $items): string
    {
        $text = '';
        $count = count($items) - 1;
        foreach ($items as $i => $item) {
            $text .= $item->text;
            if ($i === $count) {
                continue;
            }
            if (
                1 === $item->len()
                && 1 === $items[$i+1]->len()
            ) {
                continue;
            }
            // ???????????????????????????????????????????????????????????????
            if (
                $item->isWord()
                && $items[$i+1]->isSymbol()
                && !isset(self::$CUT_WORDS[$items[$i+1]->text])
            ) {
                continue;
            }
            if (
                $item->isSymbol()
                && !isset(self::$CUT_WORDS[$items[$i+1]->text])
            ) {
                continue;
            }
            $text .= ' ';
        }
        return $text;
    }

    public function getIterator(): Traversable
    {
        $this->buffer = [];
        $goBackWord = null;
        $matchCount = 0;
        while ($this->words->valid()) {
            /** @var TextNode $item */
            $item = $this->words->current();
            $this->words->next();
            // dump("input: {$item->type}|{$item->text}");

            if (0 === $matchCount
                && (
                    $item->isWrap()
                    || ($item->stat !== null && $item->stat > 0)
                    || ($item->isSymbol() && isset(self::$CUT_WORDS[$item->text]))
                )
            ) {
                yield from $this->buffer;
                if (null !== $goBackWord) {
                    yield $goBackWord;
                    $goBackWord = null;
                }
                yield $item;
                $this->buffer = [];
                continue;
            } else {
                $this->buffer[] = $item;
            }

            QUERY_MATCH:
            $wordsText = $this->joinWord($this->buffer);
            $queryText  = DictHelper::buildQueryString($wordsText);
            // dump("> {$wordsText} > {$queryText}");
            if (empty($queryText)) {
                // dump("> skip");
                continue;
            }
            $matchItems = $this->prefixQuery($queryText);
            $matchCount = count($matchItems);
            // dump("> matchCount: {$matchCount}");

            if (0 === $matchCount) {
                if (null !== $goBackWord) {
                    // ?????????????????????
                    throw new LogicException('??????????????????????????????');
                } elseif (count($this->buffer) > 1) {
                    GO_BACK:
                    // ????????????????????????????????????????????????
                    // dump("> go back");
                    $goBackWord  = array_pop($this->buffer);
                    $wordsText = $this->joinWord($this->buffer);
                    $queryText  = DictHelper::buildQueryString($wordsText);
                    $_word = $this->exactQuery($queryText);
                    if (null === $_word) {
                        // ???????????????????????????
                        yield from $this->buffer;
                        $this->buffer = [$goBackWord];
                        $goBackWord = null;
                        goto QUERY_MATCH;
                    }
                    $matchItems = [$_word];
                    goto SUCCESS;
                } else {
                    yield array_shift($this->buffer);
                }
            } elseif (1 === $matchCount) {
                if ($queryText === $matchItems[0]['query']) {
                    // dump('>-prefix:equal');
                    // ????????????
                    goto SUCCESS;
                } elseif (str_starts_with($matchItems[0]['query'], $queryText)) {
                    // dump('>-prefix');
                    // ????????????
                    $matchQuery = $matchItems[0]['query'];
                    $_tmpBuffer = [];
                    // ?????????????????????????????????
                    while ($this->words->valid()) {
                        $item = $this->words->current();
                        $this->words->next();
                        $_tmpBuffer[] = $item;
                        $wordsText = $this->joinWord(array_merge($this->buffer, $_tmpBuffer));
                        $queryText = DictHelper::buildQueryString($wordsText);
                        // dump(">-prefix: {$wordsText} > {$queryText}");
                        $matchPos = strpos($matchQuery, $queryText);
                        if (0 !== $matchPos) {
                            // dump('>-prefix:fail');
                            // ???????????????????????????
                            yield from $this->buffer;
                            $this->buffer = $_tmpBuffer;
                            continue 2;
                        }
                        if (strlen($matchQuery) === strlen($queryText)) {
                            // dump('>-prefix:success');
                            // ?????????????????????????????????
                            $this->buffer = array_merge($this->buffer, $_tmpBuffer);
                            goto SUCCESS;
                        }
                    }
                    if (true === $this->words->valid()) {
                        throw new LogicException('?????????????????????????????????????????????');
                    }
                    // ??????????????????????????????
                    yield from $this->buffer;
                    $this->buffer = $_tmpBuffer;
                    continue;
                } else {
                    // ??????????????????
                    continue;
                }

                SUCCESS: {
                    if (count($matchItems) > 1) {
                        throw new LogicException('??????????????????????????????');
                    }
                    if (count($this->buffer) > 1) {
                        $lastKey = array_key_last($this->buffer);
                        // ???????????????????????????
                        if (!$this->buffer[0]->isWord()) {
                            yield array_shift($this->buffer);
                        } elseif (
                            $this->buffer[$lastKey]->isSymbol()
                            // ??????????????????????????????
                            && isset(self::$CUT_WORDS[$this->buffer[$lastKey]->text])
                        ) {
                            $_tmpSymbolLast = array_pop($this->buffer);
                        }
                    }
                    // ????????????
                    $wct = WordsCombineText::makeFromNodes($this->buffer);
                    $text = $wct->buildString();
                    $this->buffer = [];

                    $_word = $matchItems[0];
                    yield $this->buildText($text, $_word);

                    if (isset($_tmpSymbolLast)) {
                        yield $_tmpSymbolLast;
                        $_tmpSymbolLast = null;
                    }
                    if (null !== $goBackWord) {
                        yield $goBackWord;
                        $goBackWord = null;
                    }
                }
            } elseif (!$this->words->valid() && $matchCount > 1) {
                if (count($this->buffer) > 2) {
                    goto GO_BACK;
                } elseif (1 === count($this->buffer)) {
                    yield from $this->buffer;
                    $this->buffer = [];
                }
            }
        }
        if (!empty($this->buffer)) {
            yield from $this->buffer;
        }
    }
}
