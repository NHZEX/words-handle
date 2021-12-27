<?php

namespace app\Service\TextWord;

use function in_array;
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
    ];

    protected static ?array $lengthIndex = null;

    public static function findSymbol(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }
        return self::UNIT_DICT[strtolower($text)] ?? null;
    }

    public static function isNumberOperator(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }
        if ('X' === $text || 'x' === $text) {
            $text = '*';
        } else if ('≈' === $text) {
            $text = '=';
        }
        return in_array($text, TextConstants::SYMBOL_OPERATOR) ? $text : null;
    }
}
