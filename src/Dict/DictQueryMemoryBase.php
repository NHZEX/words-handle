<?php

namespace Zxin\TextWord\Dict;

use Fuse\Fuse;
use function array_map;
use function count;
use function sprintf;

abstract class DictQueryMemoryBase
{
    private Fuse $fuse;

    public const DICT_ITEM_COUNT = 3000;

    public function __construct(
        private bool $enableBigDictWarning = true,
    )
    {
        $this->load();
    }

    abstract protected function getDict(): array;

    abstract protected function getKeys(): array;

    public function load(): void
    {
        $dict = $this->getDict();
        if ($this->enableBigDictWarning && count($dict) > self::DICT_ITEM_COUNT) {
            throw new \LogicException(
                sprintf('try to load big dictionary (>%d)', self::DICT_ITEM_COUNT)
            );
        }
        $options = [
            'keys' => $this->getKeys(),
            'includeScore' => true,
            'includeMatches' => false,
            'threshold' => 0.0,
            'useExtendedSearch' => true,
        ];
        $this->fuse = new Fuse($dict, $options);
    }

    public function query(string $text, int $limit = -1): array
    {
        $result = $this->fuse->search($text, [
            'limit' => $limit,
        ]);
        return array_map(fn ($v) => $v['item'], $result);
    }

    public function exactQuery(string $text, int $limit = -1): array
    {
        return $this->query("={$text}", $limit);
    }

    public function prefixQuery(string $text, int $limit = -1): array
    {
        return $this->query("^{$text}", $limit);
    }
}
