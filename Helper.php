<?php

namespace app\Service\TextWord;

use function str_replace;

class Helper
{
    public static function filterSymbol(string $input): string
    {
        return str_replace([
            '&nbsp;',
            "\xc2\xa0", // NBSP
            "\u{200b}", // Zero Width Space, 0xE2808B
            '、',
            '。',
            '‘',
            '’',
            '“',
            '”',
            '≈',
        ], [
            ' ',
            ' ',
            '',
            ',',
            '.',
            '\'',
            '\'',
            '"',
            '"',
            '=',
        ], $input);
    }
}
