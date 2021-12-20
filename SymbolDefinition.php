<?php

namespace app\Service\TextWord;

use function array_flip;

final class SymbolDefinition
{
    const LENGTH = [
        'm',
        'cm',
        'Cm',
        'mm',
        'Mm',
        'μm',
        'nm',
        'km',
        'Km',
        'inch',
        'foot',
        'yard',
        'mile',
        'in',
        'ft',
        'lb',
        'LB',
        'oz',
        'OZ',
        'acre',
        'pound',
        'ounce',
        'gallon',
        'cup',
    ];

    const OTHER = [
        // 质量
        'L',
        'mL',
        // 质量
        'kg',
        'Kg',
        'g',
        'G',
        // 温度
        '°C',
        '°F',
        'K',
    ];

    protected static ?array $lengthIndex = null;
    protected static ?array $otherIndex = null;

    public static function isSymbol(string $text): bool
    {
        if (null === self::$lengthIndex) {
            self::$lengthIndex = array_flip(self::LENGTH);
        }
        if (null === self::$otherIndex) {
            self::$otherIndex = array_flip(self::OTHER);
        }

        return isset(self::$lengthIndex[$text]) || isset(self::$otherIndex[$text]);
    }
}
