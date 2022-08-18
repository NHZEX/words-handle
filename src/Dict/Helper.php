<?php

namespace zxin\TextWord\Dict;

use function preg_replace;
use function strtolower;

class Helper
{
    public static function buildQueryString(string $word): string
    {
        return strtolower(preg_replace('/[^A-Z^0-9]+/ui', '', $word));
    }
}
