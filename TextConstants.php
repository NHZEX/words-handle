<?php

namespace app\Service\TextWord;

class TextConstants
{

    public const TYPE_WORD    = 'w';
    public const TYPE_NUMBER  = 'n';
    public const TYPE_SYMBOL  = 'o';
    public const TYPE_LF      = 'lf';
    public const TYPE_SPACE   = 'sp';
    public const TYPE_UNKNOWN = 'un';

    public const POSTAG_NULL    = 0;
    public const POSTAG_NUMBER  = 0x0001;
    public const POSTAG_UNIT    = 0x0002;
    public const POSTAG_SYMBOL  = 0x0004;

    // 连接符，与字符结合没有空格
    public const SYMBOL_LINK     = ['′', '–', '—', '*', '≈'];
    public const SYMBOL_OPERATOR = ['+', '-', '*', '/', '='];
    // 各种括号
    public const SYMBOL_BRACKETS_A = ['(', '[', '{'];
    public const SYMBOL_BRACKETS_B = [')', ']', '}'];
    public const SYMBOL_QUOTATION  = '"';
    public const SYMBOL_APOSTROPHE = '\'';
    // 分割符，与字符结合有空格
    public const SYMBOL_CUT = [',', '.', '?', '!', ';'];
    // 分割符，切断文本分析
    public const SYMBOL_SEG = [':'];
    // 换行符，切断文本分析
    public const SYMBOL_LF = "\n";

    public const FORCE_UPPER = [
        // 单词
        'i',
        'ok',
        // 缩写词
        'wto',
        'diy',
        'fda',
        'fyi',
        'asap',
        'sos',
        'fbi',
        'ibm',
        'bbq',
        'pp',
        'abs',
        // 单位
        'pc',
    ];

    public const FORCE_LOWER = [
        // 介词，连词，冠词
        'in', 'on', 'with', 'by', 'for', 'at',
        'about', 'under', 'of', 'before', 'after',
        'above', 'across', 'against', 'along',
        'alongside', 'among', 'around', 'as',
        'behind', 'below', 'beneath', 'beside', 'besides',
        'between', 'beyond', 'but', 'despite', 'down',
        'during', 'except', 'from', 'inside', 'into',
        'like', 'near', 'off', 'opposite',
        'outside', 'over', 'past', 'regarding',
        'round', 'since', 'through', 'throughout', 'till',
        'to', 'toward', 'until', 'up', 'upon', 'within', 'without',
        'and', 'or', 'nor', 'so', 'the', 'a', 'an',
    ];

    public const BLOCK_FORCE_LOWER = [
        // 介词，连词，冠词
        'in'      => [
            ['in', 'spite', 'of'],
        ],
        'because' => [
            ['because', 'of'],
        ],
        'out'     => [
            ['out', 'of'],
        ],
    ];

    public const MONTH = [
        'january', 'february', 'march', 'april', 'may', 'june',
        'july', 'august', 'september', 'october', 'november', 'december',
        'jan.', 'feb.', 'mar.', 'apr.', 'may.', 'jun.',
        'jul.', 'aug.', 'sept.', 'oct.', 'nov.', 'dec.',
        'jan', 'feb', 'mar', 'apr', 'may', 'jun',
        'jul', 'aug', 'sept', 'oct', 'nov', 'dec',
    ];

    public const WEEK = [
        'monday', 'mon', 'mon.',
        'tuesday', 'tues', 'tues.',
        'wednesday', 'wed', 'wed.',
        'thursday', 'thur', 'thurs', 'thur.', 'thurs.',
        'friday', 'fri', 'fri.',
        'saturday', 'sat', 'sat.',
        'sunday', 'sun', 'sun.',
    ];
}
