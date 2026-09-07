<?php

namespace Tests\Unit;

use App\Services\Safety\RiskMatrix;
use PHPUnit\Framework\TestCase;

class RiskMatrixTest extends TestCase
{
    public function test_rating_is_likelihood_times_severity(): void
    {
        $this->assertSame(1, RiskMatrix::rating(1, 1));
        $this->assertSame(12, RiskMatrix::rating(3, 4));
        $this->assertSame(25, RiskMatrix::rating(5, 5));
    }

    public function test_rating_is_clamped_to_1_5(): void
    {
        $this->assertSame(25, RiskMatrix::rating(9, 9));
        $this->assertSame(1, RiskMatrix::rating(0, 0));
    }

    public function test_levels_band_correctly(): void
    {
        $this->assertSame('low', RiskMatrix::level(1));      // 1x1
        $this->assertSame('low', RiskMatrix::level(3));      // 1x3
        $this->assertSame('medium', RiskMatrix::level(4));   // 2x2
        $this->assertSame('medium', RiskMatrix::level(6));   // 2x3
        $this->assertSame('high', RiskMatrix::level(8));     // 2x4
        $this->assertSame('high', RiskMatrix::level(12));    // 3x4
        $this->assertSame('critical', RiskMatrix::level(15)); // 3x5
        $this->assertSame('critical', RiskMatrix::level(25)); // 5x5
    }
}
