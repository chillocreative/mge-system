<?php

namespace App\Services\MonthlyReport;

/**
 * Single source of truth for which section keys accept uploaded "attached page" assets
 * (PDF/PNG/JPG pages inserted right after the section in the PDF export, or embedded/listed
 * in the DOCX export) instead of — or in addition to — their auto-generated content.
 *
 * 2.2 PHYSICAL S-CURVE and 2.4 FINANCIAL S-CURVE accept uploaded chart pages the same way
 * 2.5 ACTUAL WORK PROGRESS accepts uploaded Gantt chart pages.
 */
final class AttachedPages
{
    /** @var array<string, string> section key => MonthlyReportAsset kind */
    public const KINDS = [
        '2.2' => 'scurve_physical_page',
        '2.4' => 'scurve_financial_page',
        '2.5' => 'gantt_page',
    ];

    public static function kindFor(string $key): ?string
    {
        return self::KINDS[$key] ?? null;
    }

    public static function keyFor(string $kind): ?string
    {
        $key = array_search($kind, self::KINDS, true);

        return $key === false ? null : $key;
    }

    public static function isPageKind(string $kind): bool
    {
        return in_array($kind, self::KINDS, true);
    }
}
