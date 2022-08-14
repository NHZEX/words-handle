<?php

namespace app\Service\TextWord;

use function array_keys;
use function array_map;
use function in_array;
use function max;
use function strlen;
use function strtolower;

final class SymbolDefinition
{
    public const UNIT_DICT = [
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

    protected static ?int $length = null;

    public static function findSymbol(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }
        if (null === self::$length) {
            self::$length = max(...array_map('\strlen', array_keys(self::UNIT_DICT)));
        }
        if (strlen($text) > self::$length) {
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
        } elseif ('≈' === $text) {
            $text = '=';
        }
        if (in_array($text, TextConstants::SYMBOL_OPERATOR)) {
            return '*' === $text ? 'x' : $text;
        }
        return null;
    }
}
