<?php

namespace Tests\Unit;

use App\Services\PerformanceLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceLevelTest extends TestCase
{
    use RefreshDatabase;
    public function test_reference_boundaries_and_colors_are_exhaustive(): void
    {
        $scale = new PerformanceLevel();

        $this->assertSame(['exceptional', '#00B050'], $this->keyAndColor($scale, 95));
        $this->assertSame(['high', '#92D050'], $this->keyAndColor($scale, 90));
        $this->assertSame(['satisfactory', '#FFC000'], $this->keyAndColor($scale, 89.99));
        $this->assertSame(['satisfactory', '#FFC000'], $this->keyAndColor($scale, 85));
        $this->assertSame(['needs_improvement', '#FF0000'], $this->keyAndColor($scale, 84.99));
        $this->assertSame(['needs_improvement', '#FF0000'], $this->keyAndColor($scale, 75));
        $this->assertSame(['unsatisfactory', '#C00000'], $this->keyAndColor($scale, 74.99));
        $this->assertSame('not_applicable', $scale->for(null)['key']);
    }

    private function keyAndColor(PerformanceLevel $scale, float $value): array
    {
        $level = $scale->for($value);
        return [$level['key'], $level['hex']];
    }
}
