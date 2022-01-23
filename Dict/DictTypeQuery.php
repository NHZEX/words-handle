<?php

namespace app\Service\TextWord\Dict;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;

final class DictTypeQuery extends DictQueryBase
{
    protected function getDict(): array
    {
        return (new AmazonWordDictModel)
            ->whereIn('genre', [WordFilterEnum::_BAD, WordFilterEnum::_WARN])
            ->column(['id', 'genre', 'word', 'query']);
    }

    protected function getKeys(): array
    {
        return ['query'];
    }
}
