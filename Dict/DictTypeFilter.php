<?php

namespace app\Service\TextWord\Dict;

use app\Service\TextWord\TextNode;

/**
 * @implements \IteratorAggregate<string, TextNode>
 */
class DictTypeFilter extends DictFilterBase
{
    protected function exactQuery(string $queryText): ?array
    {
        $data = DictQueryBadAndWarn::findEqualRaw($queryText);
        return $data ? $data->toArray() : null;
    }

    protected function prefixQuery(string $queryText): array
    {
        return DictQueryBadAndWarn::findPhraseRaw($queryText, 2)->toArray();
    }

    protected function buildText(string $text, array $word): TextNode
    {
        return TextNode::makeWord($text, $word['genre']);
    }
}
