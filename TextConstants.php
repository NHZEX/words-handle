<?php

namespace app\Service\TextWord;

class TextConstants
{
    public const TYPE_WORD   = 'w';
    public const TYPE_NUMBER = 'n';
    public const TYPE_SYMBOL = 'o';
    public const TYPE_LF     = 'lf';

    // 连接符，与字符结合没有空格
    public const SYMBOL_LINK       = ['/', '′', '-', '–', '—', '=', '*', '≈', '°'];
    // 各种括号
    public const SYMBOL_BRACKETS_A = ['(', '[', '{'];
    public const SYMBOL_BRACKETS_B = [')', ']', '}'];
    public const SYMBOL_QUOTE            = '"';
    public const SYMBOL_SINGLE_QUOTATION = '\'';
    // 分割符，与字符结合有空格
    public const SYMBOL_CUT        = [',', '.', '?', '!', ';'];
    // 分割符，切断文本分析
    public const SYMBOL_SEG        = [':'];
    // 换行符，切断文本分析
    public const SYMBOL_LF         = "\n";
}
