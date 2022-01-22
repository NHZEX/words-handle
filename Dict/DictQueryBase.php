<?php

namespace app\Service\TextWord\Dict;

use Fuse\Fuse;
use function array_map;

abstract class DictQueryBase
{
    private Fuse $fuse;

    public function __construct()
    {
        $this->load();
    }

    abstract protected function getDict(): array;

    abstract protected function getKeys(): array;

    public function load()
    {
        $options = [
            'keys' => $this->getKeys(),
            'includeScore' => true,
            'includeMatches' => false,
            'threshold' => 0.0,
            'useExtendedSearch' => true,
        ];
        $this->fuse = new Fuse($this->getDict(), $options);
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
