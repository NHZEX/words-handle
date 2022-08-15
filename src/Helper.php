<?php

namespace zxin\TextWord;

use function str_replace;

class Helper
{
    public static function filterSymbol(string $input): string
    {
        return str_replace([
            '&nbsp;',
            '&lrm;',
            "\xc2\xa0", // NBSP
            "\u{200b}", // Zero Width Space, 0xE2808B
            "\xe2\x80\x8e", // &lrm;
            '、',
            '。',
            '‘',
            '’',
            '“',
            '”',
            '≈',
        ], [
            ' ',
            '',
            ' ',
            '',
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
