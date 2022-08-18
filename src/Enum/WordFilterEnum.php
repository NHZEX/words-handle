<?php

namespace Zxin\TextWord\Enum;

final class WordFilterEnum
{
    protected const BAD  = 1;
    protected const WARN = 2;
    protected const BASE = 3;

    public const _BAD  = self::BAD;
    public const _WARN   = self::WARN;
    public const _BASE   = self::BASE;

    private const DESC = [
        self::BAD  => '违禁词',
        self::WARN => '敏感词',
        self::BASE => '常规词',
    ];

    public static function valueToDesc(string $value): string
    {
        return self::DESC[$value] ?? '未知';
    }
}
