<?php

namespace App\Services\Safety;

/**
 * Standard 5×5 HIRARC risk matrix (DOSH Malaysia convention).
 *
 * Risk rating = likelihood (1–5) × severity (1–5), giving 1–25, banded into
 * four levels. Kept in one place so the rating and its band can never drift
 * apart between the API, the stored row and the UI.
 */
class RiskMatrix
{
    public static function rating(int $likelihood, int $severity): int
    {
        $l = max(1, min(5, $likelihood));
        $s = max(1, min(5, $severity));

        return $l * $s;
    }

    public static function level(int $rating): string
    {
        return match (true) {
            $rating >= 15 => 'critical',
            $rating >= 8 => 'high',
            $rating >= 4 => 'medium',
            default => 'low',
        };
    }
}
