<?php

namespace Zxin\Tests\TextWord;

use Zxin\TextWord\TextSegment;
use PHPUnit\Framework\TestCase;

class TextSliceTextTest extends TestCase
{
    public function textSliceProvider(): \Generator
    {
        yield [
            'Size: 22 x5x5 cm/ 8.66 x 1.97 * 1.97inch.',
            ['Size', ':', '22', 'x', '5', 'x', '5', 'cm', '/', '8.66', 'x', '1.97', '*', '1.97', 'inch', '.'],
        ];
        yield [
            <<<TEXT
            Product Description
            This Jesus Door
            
            
            DETAILS:
            
            Package includes
            TEXT,
            ['Product', 'Description', "\n", 'This', 'Jesus', 'Door', "\n", "\n", "\n", 'DETAILS', ':', "\n", "\n", 'Package', 'includes'],
        ];

        yield [
            <<<TEXT
            Product Description神経感覚
            This Jesus Door
            TEXT,
            ['Product', 'Description', '神経感覚', "\n", 'This', 'Jesus', 'Door'],
        ];

        yield [
            'FUNCTION: 神経感覚 apple repel any pest bba',
            ['FUNCTION', ':', '神経感覚', 'apple', 'repel', 'any', 'pest', 'bba'],
        ];

        yield [
            'Product Description YSX Ampai\'s This Jesus',
            ['Product', 'Description', 'YSX', 'Ampai\'s', 'This', 'Jesus'],
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
