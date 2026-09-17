<?php

namespace App\Support;

class RinggitWords
{
    private const ONES = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];

    private const TENS = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    private const SCALES = [1000000000 => 'Billion', 1000000 => 'Million', 1000 => 'Thousand'];

    public static function spell(float|string $amount): string
    {
        $cents = (int) round(((float) $amount) * 100);
        $ringgit = intdiv($cents, 100);
        $sen = $cents % 100;

        $words = $ringgit === 0 ? 'Zero' : self::number($ringgit);
        $result = $words.' Ringgit';
        if ($sen > 0) {
            $result .= ' And '.self::number($sen).' Sen';
        }

        return $result.' Only';
    }

    private static function number(int $n): string
    {
        $parts = [];
        foreach (self::SCALES as $value => $label) {
            if ($n >= $value) {
                $parts[] = self::number(intdiv($n, $value)).' '.$label;
                $n %= $value;
            }
        }
        if ($n >= 100) {
            $parts[] = self::ONES[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }
        if ($n >= 20) {
            $tens = self::TENS[intdiv($n, 10)];
            $parts[] = $n % 10 ? $tens.'-'.self::ONES[$n % 10] : $tens;
        } elseif ($n > 0) {
            $parts[] = self::ONES[$n];
        }

        return implode(' ', $parts);
    }
}
