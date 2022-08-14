<?php

namespace app\Service\TextWord\Dict;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;

final class DictDecoratorQuery extends DictQueryBase
{
    protected function getDict(): array
    {
        return (new AmazonWordDictModel())
            ->where('genre', WordFilterEnum::_BASE)
            ->column(['id', 'genre', 'word', 'query', 'options']);
    }

    protected function getKeys(): array
    {
        return ['query'];
    }
}
