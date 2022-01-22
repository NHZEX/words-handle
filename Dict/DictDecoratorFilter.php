<?php

namespace app\Service\TextWord\Dict;

use app\Service\TextWord\TextNode;
use function json_decode_ex;
use function strtoupper;

class DictDecoratorFilter extends DictFilterBase
{
    private DictDecoratorQuery $dict;

    protected function initDict()
    {
        parent::initDict();

        $this->dict = new DictDecoratorQuery();
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
        if ($word['options'] && $_options = json_decode_ex($word['options'])) {
            $convFormat = $_options['convFormat'] ?? 0;
            if (1 === $convFormat) {
                $text = strtoupper($text);
            }
        }
        return TextNode::makeWord($text, 0);
    }
}
