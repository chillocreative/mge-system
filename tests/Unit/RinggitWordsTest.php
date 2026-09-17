<?php

namespace Tests\Unit;

use App\Support\RinggitWords;
use PHPUnit\Framework\TestCase;

class RinggitWordsTest extends TestCase
{
    public function test_whole_ringgit(): void
    {
        $this->assertSame('Two Hundred Eighty-Eight Million Ringgit Only', RinggitWords::spell(288000000));
        $this->assertSame('Fourteen Million Four Hundred Thousand Ringgit Only', RinggitWords::spell('14400000.00'));
        $this->assertSame('One Ringgit Only', RinggitWords::spell(1));
    }

    public function test_with_sen(): void
    {
        $this->assertSame('Fifty-Two Thousand One Hundred Twenty-Eight Ringgit And Fifty Sen Only', RinggitWords::spell(52128.50));
    }

    public function test_zero(): void
    {
        $this->assertSame('Zero Ringgit Only', RinggitWords::spell(0));
    }
}
