<?php

namespace App\Services\MonthlyReport\Export\Docx;

/**
 * Column layouts for RowsWriter, mirroring the columns/labels/formatting of the PDF Blade
 * partials (resources/views/pdf/monthly-report/sections/s*.blade.php) exactly, so the DOCX
 * and PDF exports read the same.
 */
final class SectionSpecs
{
    /**
     * @return array{rowsKey: string, columns: array<int, array{key: string, label: string, type?: string, default?: mixed}>, preamble?: string}|null
     */
    public static function for(string $key): ?array
    {
        return match ($key) {
            '1.2' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'party', 'label' => 'Party'],
                    ['key' => 'company', 'label' => 'Company'],
                    ['key' => 'address', 'label' => 'Address'],
                    ['key' => 'contacts', 'label' => 'Contacts', 'type' => 'contacts'],
                ],
            ],
            '1.5' => [
                'rowsKey' => 'rows',
                'preamble' => 'company',
                'columns' => [
                    ['key' => 'designation', 'label' => 'Designation'],
                    ['key' => 'nos', 'label' => 'Nos.', 'default' => 0],
                ],
            ],
            '2.3' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'ipc_no', 'label' => 'IPC No.'],
                    ['key' => 'submission_date', 'label' => 'Submission', 'default' => '-'],
                    ['key' => 'evaluation_date', 'label' => 'Evaluation', 'default' => '-'],
                    ['key' => 'claim_amount', 'label' => 'Claim Amount', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'certified', 'label' => 'Certified', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'wjp_current', 'label' => 'WJP Current', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'wjp_cumulative', 'label' => 'WJP Cumulative', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'paid_current', 'label' => 'Paid Current', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'paid_cumulative', 'label' => 'Paid Cumulative', 'type' => 'money', 'align' => 'r'],
                    ['key' => 'remarks', 'label' => 'Remarks'],
                ],
            ],
            '2.6' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'no', 'label' => 'No.'],
                    ['key' => 'title', 'label' => 'Title'],
                    ['key' => 'issue', 'label' => 'Issue'],
                    ['key' => 'reg_number', 'label' => 'Reg. No.'],
                    ['key' => 'submitted', 'label' => 'Submitted', 'default' => '-'],
                    ['key' => 'reply', 'label' => 'Reply', 'default' => '-'],
                    ['key' => 'duration', 'label' => 'Duration'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'impact', 'label' => 'Impact'],
                ],
            ],
            '2.5' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'no', 'label' => 'No.'],
                    ['key' => 'task', 'label' => 'Task'],
                    ['key' => 'duration', 'label' => 'Duration'],
                    ['key' => 'start', 'label' => 'Start'],
                    ['key' => 'finish', 'label' => 'Finish'],
                    ['key' => 'actual', 'label' => 'Actual'],
                    ['key' => 'plan', 'label' => 'Plan'],
                ],
            ],
            '3.4' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'no', 'label' => 'No.'],
                    ['key' => 'ref_no', 'label' => 'Ref. No.'],
                    ['key' => 'name', 'label' => 'Name'],
                    ['key' => 'date', 'label' => 'Date', 'default' => '-'],
                    ['key' => 'result', 'label' => 'Result'],
                    ['key' => 'remarks', 'label' => 'Remarks'],
                ],
            ],
            '3.6' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'no', 'label' => 'No.'],
                    ['key' => 'drawing_no', 'label' => 'Drawing No.'],
                    ['key' => 'title', 'label' => 'Title'],
                ],
            ],
            '3.7' => [
                'rowsKey' => 'rows',
                'columns' => [
                    ['key' => 'no', 'label' => 'No.'],
                    ['key' => 'description', 'label' => 'Description'],
                    ['key' => 'date', 'label' => 'Date', 'default' => '-'],
                    ['key' => 'location', 'label' => 'Location'],
                ],
            ],
            default => null,
        };
    }
}
