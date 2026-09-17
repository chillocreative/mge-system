<?php

namespace App\Services\MonthlyReport;

use App\Services\MonthlyReport\Sections as S;
use InvalidArgumentException;

final class SectionRegistry
{
    public const TITLES = [
        'cover' => 'Cover & Signatories',
        '1.1' => '1.1 PROJECT INFORMATION',
        '1.2' => '1.2 CONTRACT CORRESPONDENCE',
        '1.3' => '1.3 PROJECT LOCATION',
        '1.4' => '1.4 ORGANISATION CHART',
        '1.5' => '1.5 RESOURCE PLANNING',
        '2.1' => '2.1 SUMMARY WORK PROGRESS',
        '2.2' => '2.2 PHYSICAL S-CURVE',
        '2.3' => '2.3 SUMMARY OF PROGRESS CLAIM',
        '2.4' => '2.4 FINANCIAL S-CURVE',
        '2.5' => '2.5 ACTUAL WORK PROGRESS',
        '2.6' => '2.6 NOTICE OF DELAY',
        '3.1' => '3.1 SUMMARY OF DOCUMENTS SUBMISSION',
        '3.2' => '3.2 LIST OF PENDING CORRESPONDENCE',
        '3.4' => '3.4 LIST OF TESTING AND COMMISSIONING',
        '3.6' => '3.6 LIST OF TENDER DRAWING',
        '3.7' => '3.7 LIST OF MEETING / DISCUSSION',
        '4.1' => '4.1 MONTHLY TRADE WORKER',
        '4.2' => '4.2 MONTHLY MACHINERIES & PLANT',
        '4.3' => '4.3 WEATHER REPORT',
        '5.0' => '5.0 PROGRESS PHOTOGRAPH',
    ];

    /** @return array<string, class-string<SectionBuilder>> */
    public static function all(): array
    {
        return [
            'cover' => S\CoverBuilder::class,
            '1.1' => S\ProjectInformationBuilder::class,
            '1.2' => S\ContractCorrespondenceBuilder::class,
            '1.3' => S\PlaceholderBuilder::class,
            '1.4' => S\OrganisationChartBuilder::class,
            '1.5' => S\ResourcePlanningBuilder::class,
            '2.1' => S\WorkProgressBuilder::class,
            '2.2' => S\PhysicalSCurveBuilder::class,
            '2.3' => S\ProgressClaimBuilder::class,
            '2.4' => S\FinancialSCurveBuilder::class,
            '2.5' => S\WorkProgrammeBuilder::class,
            '2.6' => S\DelayNoticeBuilder::class,
            '3.1' => S\DocumentSubmissionBuilder::class,
            '3.2' => S\PendingCorrespondenceBuilder::class,
            '3.4' => S\TestingBuilder::class,
            '3.6' => S\TenderDrawingBuilder::class,
            '3.7' => S\MeetingListBuilder::class,
            '4.1' => S\PlaceholderBuilder::class,
            '4.2' => S\PlaceholderBuilder::class,
            '4.3' => S\PlaceholderBuilder::class,
            '5.0' => S\PlaceholderBuilder::class,
        ];
    }

    public static function make(string $key): SectionBuilder
    {
        $class = self::all()[$key] ?? throw new InvalidArgumentException("Unknown section {$key}");

        return new $class($key);
    }

    public static function titles(): array
    {
        return self::TITLES;
    }
}
