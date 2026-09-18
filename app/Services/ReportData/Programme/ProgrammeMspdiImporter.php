<?php

namespace App\Services\ReportData\Programme;

use Illuminate\Validation\ValidationException;

class ProgrammeMspdiImporter
{
    /**
     * @return array<int, array{name: string, outline_level: int, duration_days: ?int, start: ?string, finish: ?string, actual_pct: ?float, plan_pct: ?float, is_summary: bool}>
     */
    public function import(string $absolutePath): array
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $xml = @simplexml_load_file($absolutePath, \SimpleXMLElement::class, LIBXML_NONET);

        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if ($xml === false || $errors !== []) {
            throw ValidationException::withMessages([
                'file' => 'The file is not a valid MS Project XML export.',
            ]);
        }

        $namespaces = $xml->getNamespaces(true);
        $defaultNs = $namespaces[''] ?? null;

        $root = $defaultNs ? $xml->children($defaultNs) : $xml;
        $tasksNode = $root->Tasks ?? null;

        if ($tasksNode === null) {
            throw ValidationException::withMessages([
                'file' => 'The file is not a valid MS Project XML export.',
            ]);
        }

        $taskList = $defaultNs ? $tasksNode->children($defaultNs) : $tasksNode;

        $activities = [];

        foreach ($taskList->Task ?? [] as $task) {
            $node = $defaultNs ? $task->children($defaultNs) : $task;

            $uid = isset($node->UID) ? (string) $node->UID : null;
            if ($uid === '0') {
                continue;
            }

            $name = isset($node->Name) ? (string) $node->Name : '';
            if (trim($name) === '') {
                continue;
            }

            $outlineLevel = isset($node->OutlineLevel) && is_numeric((string) $node->OutlineLevel)
                ? ActivityNormaliser::clampOutlineLevel((int) (string) $node->OutlineLevel)
                : 1;

            $duration = isset($node->Duration) ? ActivityNormaliser::durationDays((string) $node->Duration) : null;
            $start = isset($node->Start) ? ActivityNormaliser::date((string) $node->Start) : null;
            $finish = isset($node->Finish) ? ActivityNormaliser::date((string) $node->Finish) : null;
            $actualPct = isset($node->PercentComplete) ? ActivityNormaliser::percent((string) $node->PercentComplete, false) : null;
            $isSummary = isset($node->Summary) && (string) $node->Summary === '1';

            $activities[] = [
                'name' => ActivityNormaliser::clampName($name),
                'outline_level' => $outlineLevel,
                'duration_days' => $duration,
                'start' => $start,
                'finish' => $finish,
                'actual_pct' => $actualPct,
                'plan_pct' => null,
                'is_summary' => $isSummary,
            ];
        }

        return ActivityNormaliser::deriveSummaries($activities);
    }
}
