<?php

namespace Zxin\TextWord\Dict;

use function preg_replace;
use function strtolower;

class DictHelper
{
    private static string $buildQueryStrFilterPreg = '/[^A-Z^0-9]+/ui';

    private static \Closure|null $build = null;

    public static function buildQueryString(string $word): string
    {
        if (null === self::$build) {
            return strtolower(preg_replace(self::$buildQueryStrFilterPreg, '', $word));
        } else {
            return self::$build->__invoke($word);
        }
    }

    public static function setBuildFunction(?\Closure $func): void
    {
        self::$build = $func;
    }

    public static function setBuildQueryStrFilterPreg(string $buildQueryStrFilterPreg): void
    {
        self::$buildQueryStrFilterPreg = $buildQueryStrFilterPreg;
    }
}
