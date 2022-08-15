<?php

namespace zxin\TextWord\Dict;

use zxin\TextWord\TextNode;

/**
 * @implements \IteratorAggregate<string, TextNode>
 */
class DictTypeFilter extends DictFilterBase
{
    private DictTypeQuery $dict;

    protected function initDict()
    {
        parent::initDict();

        $this->dict = new DictTypeQuery();
    }

    protected function exactQuery(string $queryText): ?array
    {
        return $this->dict->exactQuery($queryText, 1)[0] ?? null;
    }

    protected function prefixQuery(string $queryText): array
    {
        return $this->dict->prefixQuery($queryText, 2);
    }

    protected function buildText(string $text, array $word): TextNode
    {
        return TextNode::makeWord($text, $word['genre']);
    }
}
