<?php

namespace Zxin\Tests\TextWord;

use Zxin\TextWord\TextSegment;
use Zxin\TextWord\WordsCombineText;
use PHPUnit\Framework\TestCase;

class WordsCombineTextTest extends TestCase
{
    public function textFormatProvider(): \Generator
    {
        yield [
            'Square Foaming Bottle Press -type Foaming Bottle Plastic Foaming Bottle Press Foaming Bottle Multifunctional Foaming Bottle Shampoo Dispenser Pump Bottle',
            'Square Foaming Bottle Press-type Foaming Bottle Plastic Foaming Bottle Press Foaming Bottle Multifunctional Foaming Bottle Shampoo Dispenser Pump Bottle',
        ];
        yield [
            'Red Rose Pliers DIY 14.3CMx2.5Cm=15cm Red 10:55pm Iron Rose Tongs 55°c 55°f 55°C 55°F Deburring Tool Suitable for Florist and Garden Flowers Depilation 10:55',
            'Red Rose Pliers DIY 14.3 cm x 2.5 cm = 15 cm Red 10:55PM Iron Rose Tongs 55°C 55°F 55°C 55°F Deburring Tool Suitable for Florist and Garden Flowers Depilation 10:55',
        ];
        yield [
            'Red Rose Pliers DIY 14.3cmx2.5cm=15cm. red.',
            'Red Rose Pliers DIY 14.3 cm x 2.5 cm = 15 cm. Red.',
        ];
        yield [
            '15cm... red.',
            '15 cm... Red.',
        ];
        yield [
            '1 inch=2.45cm/1cm≈0.393inch',
            '1 inch = 2.45 cm / 1 cm = 0.393 inch',
        ];
        yield [
            '2.Please allow 1-3 cm error due to manual measurement. pls make sure you do not mind before you bid.',
            '2. Please allow 1 - 3 cm error due to manual measurement. Pls make sure you do not mind before you bid.',
        ];
        yield [
            'to cn Is',
            'To cn Is',
        ];
        yield [
            <<<TEXT
            FEATURES:
            FUNCTION: this desk tidy is elegant in its simplicity, and thoughtfully created with details that help you stay organized.
            APPLICATION: multi-function organizer has multiple uses and is perfect for home, craft and hobby items, offices, desktops, etc.
            USAGE: rich storage space to effectively meet your daily storage needs.
            MATERIAL: made of quality PP material, durable, and it is also easy to clean and dirt resistant.
            DESIGN: slip-resistant and Nice bottom won 't scratch bookcases.
            SPECIFICATION:
            Color: Light pink
            Weight: 173g
            Quantity: 1 pc
            NOTE:
            Due to the light and screen setting difference, the item' s color may be slightly difference from the pictures.
            Please allow slight dimension difference due to different manual measurement.
            TEXT,
            <<<TEXT
            FEATURES:
            FUNCTION: This desk tidy is elegant in its simplicity, and thoughtfully created with details that help you stay organized.
            APPLICATION: Multi-function organizer has multiple uses and is perfect for home, craft and hobby items, offices, desktops, etc.
            USAGE: Rich storage space to effectively meet your daily storage needs.
            MATERIAL: Made of quality PP material, durable, and it is also easy to clean and dirt resistant.
            DESIGN: Slip-resistant and Nice bottom won't scratch bookcases.
            SPECIFICATION:
            Color: Light pink
            Weight: 173 g
            Quantity: 1 PC
            NOTE:
            Due to the light and screen setting difference, the item's color May be slightly difference from the pictures.
            Please allow slight dimension difference due to different manual measurement.
            TEXT,
        ];
        yield [
            'Text: 3D 4B BBQ A4 due P2P Test',
            'Text: 3D 4B BBQ A4 due P2P Test',
        ];
        yield [
            'Multi-Function Pen Holder Desktop Stationery Storage Box Minimalism Office Desk Organizer For Office, School, Home And Kids (Light Pink) 1 Pc',
            'Multi-function Pen Holder Desktop Stationery Storage Box Minimalism Office Desk Organizer for Office, School, Home and Kids (Light Pink) 1 PC',
            true,
        ];
        yield [
            'GREAT-GIFT:A lovely-Stunning ring-for-all occasions.',
            'Great-Gift: A lovely-stunning ring-for-all occasions.',
            false,
            false,
            WordsCombineText::STYLE_FORMAT_FEATURE,
        ];
        yield [
            'MATERIAL: Made of quality PP material, durable, and it is also easy to clean and dirt resistant.',
            'Material: Made of quality PP material, durable, and it is also easy to clean and dirt resistant.',
            false,
            false,
            WordsCombineText::STYLE_FORMAT_FEATURE,
        ];
        yield [
            "Perfect Gift for wife on Anniversary,Mother's Day,alentine's Day,Birthday,Christmas",
            "Perfect Gift for wife on Anniversary, Mother's Day, alentine's Day, Birthday, Christmas",
        ];
        yield [
            <<<TEXT
            Features:
            100% brand new and high quality.
            Wipe clean, neat, soft edges, adding comfort wipe.
            Mainly applicable to Class B pencil hardness and other mechanical pencil writing.
            Ideal for children for school usage, Sketching, drawing, art, general home and office use.
            Candy color eraser is very cute and beautiful, just like jelly.
            Description:
            Main Material:pvc
            Color:blue
            Item Size: Approx.(L*W*)36mm*21mm
            Note:
            1. 1 inch=2.45cm/1cm≈0.393inch
            2.Please allow 1-3 cm error due to manual measurement. pls make sure you do not mind before you bid.
            3.Due to the difference between different monitors, the picture may not reflect the actual color of the item. Thank you!
            TEXT,
            <<<TEXT
            Features:
            100% brand new and high quality.
            Wipe clean, neat, soft edges, adding comfort wipe.
            Mainly applicable to Class B pencil hardness and other mechanical pencil writing.
            Ideal for children for school usage, Sketching, drawing, art, general home and office use.
            Candy color eraser is very cute and beautiful, just like jelly.
            Description:
            Main Material: Pvc
            Color: Blue
            Item Size: Approx. (L*W*) 36 mm x 21 mm
            Note:
            1. 1 inch = 2.45 cm / 1 cm = 0.393 inch
            2. Please allow 1 - 3 cm error due to manual measurement. Pls make sure you do not mind before you bid.
            3. Due to the difference between different monitors, the picture May not reflect the actual color of the item. Thank you!
            TEXT,
        ];
        yield [
            'Sunday\'S football game',
            'Sunday\'s football game',
        ];
        yield [
            'Multi Function， Penholder',
            'Multi Function Penholder',
            false,
            true,
        ];
        yield [
            'Size: 22 x5x5 cm/ 8.66 x 1.97 * 1.97inch.',
            'Size: 22 x 5 x 5 cm / 8.66 x 1.97 x 1.97 inch.',
        ];
        // 介词，连词强制大小写
        yield from [
            [
                'In abc',
                'In abc',
                false,
            ], [
                'out of abc out of cba',
                'Out of abc out of cba',
                false,
            ], [
                <<<TEXT
                Description: Into manual
                into manual
                TEXT,
                <<<TEXT
                Description: Into manual
                Into manual
                TEXT,
                false,
            ],
        ];
        // bug
        yield [
            'Features: The car inner door handle is adhesive-proof, anti-fouling, anti-scratch, more durable, and perfectly matches your original car',
            'Features: The car inner door handle is adhesive-proof, anti-fouling, anti-scratch, more durable, and perfectly matches your original car',
        ];
        // bug 2
        yield [
            'Pen Holder 32° desk tidy',
            'Pen Holder 32° desk tidy',
        ];
        // bug 3
        yield [
            'Size: 13*9 cm/5.91x3.54 inch.',
            'Size: 13 x 9 cm / 5.91 x 3.54 inch.'
        ];
        yield [
            'Pen out',
            'Pen out',
        ];
        // 问题0809
        yield [
            '4 PCS Cord Organizer, Stick on Cord Winder Kitchen Appliances, Cord Wrapper Cable Management Gadgets For Appliance Cables Storage 4 Packs 5*7INCH',
            '4 Pcs Cord Organizer, Stick on Cord Winder Kitchen Appliances, Cord Wrapper Cable Management Gadgets for Appliance Cables Storage 4 Packs 5 x 7 inch',
            true,
        ];
        // 符号链接问题
        yield [
            'A&B',
            'A&B',
        ];
        // 符号链接问题
        yield [
            'BY-50',
            'By-50',
        ];
        // ddd
        yield [
            'turkey, 1 PCS',
            'Turkey, 1 Pcs',
            true,
        ];
        yield [
            'Chicken Tenders, 1Pcs Meat Hammer, easy to clean easy to use Meat Masher, Kitchen Tools for chicken, turkey',
            'Chicken Tenders, 1 Pcs Meat Hammer, Easy to Clean Easy to Use Meat Masher, Kitchen Tools for Chicken, Turkey',
            true,
        ];
    }

    /**
     * @dataProvider textFormatProvider
     * @return void
     */
    public function testTextFormat(string $input, string $expected, bool $firstLetterUpper = false, bool $filterSymbol = false, ?string $style = null)
    {
        $words = [];
        foreach (TextSegment::input($input) as $item) {
            $words[] = $item;
            // var_dump("({$item->type}) {$item->text}");
        }
        //var_dump($words);

        $wct = WordsCombineText::makeFromNodes($words);
        $wct->setForceFirstLetterUpper($firstLetterUpper);
        $wct->setFilterSymbol($filterSymbol);
        $wct->setFormatStyle($style);
        $nodes = $wct->build();
        $output = $wct->toString($nodes);

        $this->assertEquals($expected, $output);
    }
}
