<?php

namespace app\Service\TextWord;

use function strtolower;

final class SymbolDefinition
{
    const UNIT_DICT = [
        // 长度
        'μm' => 'μm',
        'nm' => 'nm',
        'mm' => 'mm',
        'cm' => 'cm',
        'm' => 'm',
        'km' => 'km',
        'in' => 'in',
        'ft' => 'ft',
        'yd' => 'yd',
        'inch' => 'inch',
        'foot' => 'foot',
        'yard' => 'yard',
        'mile' => 'mile',
        // 容量
        'fl' => 'fl', // fl oz
        'l' => 'L',
        'ml' => 'mL',
        'gal' => 'gal',
        // 重量
        'oz' => 'oz',
        'lbs' => 'lbs',
        'g' => 'g',
        'kg' => 'kg',
        'mg' => 'mg',
        // 温度
        '°C' => '°C',
        '°F' => '°F',
        // 时间
        'am' => 'AM',
        'pm' => 'PM',
    ];

    protected static ?array $lengthIndex = null;

    public static function findSymbol(string $text): ?string
    {
        return self::UNIT_DICT[strtolower($text)] ?? null;
    }
}
