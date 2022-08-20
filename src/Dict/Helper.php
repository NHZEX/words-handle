<?php

namespace Zxin\TextWord\Dict;

use function preg_replace;
use function strtolower;

class Helper
{
    private static string $buildQueryStrFilterPreg = '/[^A-Z^0-9]+/ui';

    public static function buildQueryString(string $word): string
    {
        return strtolower(preg_replace(self::$buildQueryStrFilterPreg, '', $word));
    }

    public static function setBuildQueryStrFilterPreg(string $buildQueryStrFilterPreg): void
    {
        self::$buildQueryStrFilterPreg = $buildQueryStrFilterPreg;
    }
}
