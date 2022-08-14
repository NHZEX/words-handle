<?php

namespace app\Service\TextWord\Dict;

use app\Enum\WordFilterEnum;
use app\Model\AmazonWordDictModel;
use think\Collection;

class DictQueryBadAndWarn
{
    public static function findWord(string $word, bool $cache = false): ?AmazonWordDictModel
    {
        // todo 缓冲查询
        $query = AmazonWordDictModel::buildQueryString($word);
        return (new AmazonWordDictModel())
            ->where('query', $query)
            ->whereIn('genre', [WordFilterEnum::_BAD, WordFilterEnum::_WARN])
            ->cache($cache ? "dict:check:bad_warn:{$query}" : false, 3600)
            ->find();
    }

    public static function findPhrase(string $text, int $limit = 16): ?Collection
    {
        $query = AmazonWordDictModel::buildQueryString($text);
        return self::findPhraseRaw($query, $limit);
    }

    /**
     * @return Collection|static[]
     */
    public static function findPhraseRaw(string $query, int $limit = 16): ?Collection
    {
        // todo 缓冲查询
        return (new AmazonWordDictModel())
            ->whereLike('query', "{$query}%")
            ->whereIn('genre', [WordFilterEnum::_BAD, WordFilterEnum::_WARN])
            ->limit($limit)
            ->select();
    }

    /**
     * @return AmazonWordDictModel|null
     */
    public static function findEqualRaw(string $query): ?AmazonWordDictModel
    {
        // todo 缓冲查询
        return (new AmazonWordDictModel())
            ->where('query', $query)
            ->whereIn('genre', [WordFilterEnum::_BAD, WordFilterEnum::_WARN])
            ->find();
    }
}
