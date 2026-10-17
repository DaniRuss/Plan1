<?php

namespace Tests\Unit;

use App\Support\NumberFormatter;
use PHPUnit\Framework\TestCase;

class NumberFormatterTest extends TestCase
{
    public function test_display_rounding_and_trailing_zeros(): void
    {
        foreach (['55.0000'=>'55','55.1234'=>'55.123','55.5678'=>'55.568','100.0000'=>'100','-55.5678'=>'-55.568','0.0004'=>'0','-0.0004'=>'0','9.9999'=>'10','1.2345'=>'1.235','12345.6789'=>'12,345.679'] as $input=>$expected) {
            $this->assertSame($expected, NumberFormatter::format($input));
        }
        $this->assertSame('12345.679',NumberFormatter::format('12345.6789',3,'.',''));
        $this->assertSame('',NumberFormatter::format(null));
        $this->assertSame('N/A',NumberFormatter::format('N/A'));
        $this->assertSame('-',NumberFormatter::format('-'));
    }
}
