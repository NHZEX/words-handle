<?php

namespace zxin\Tests\TextWord;

use zxin\TextWord\TextSegment;
use PHPUnit\Framework\TestCase;

class TextSliceText extends TestCase
{
    public function textSliceProvider(): \Generator
    {
        yield [
            'Size: 22 x5x5 cm/ 8.66 x 1.97 * 1.97inch.',
            ['Size', ':', '22', 'x5x5', 'cm', '/', '8.66', 'x', '1.97', '*', '1.97', 'inch', '.'],
        ];
    }

    /**
     * @dataProvider textSliceProvider
     * @return void
     */
    public function testTextSlice(string $input, array $expected)
    {
        $output = [];
        foreach (TextSegment::input($input) as $item) {
            $output[] = $item->text;
            //var_dump("({$item['type']}) {$item['text']}");
        }

        $this->assertEquals($expected, $output);
    }
}
