<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectInvoice;
use App\Services\MonthlyReport\ReportContext;

final class ProgressClaimBuilder extends AbstractBuilder
{
    private const STATUS_LABELS = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'approved' => 'Approved',
        'paid' => 'Paid',
    ];

    public function build(ReportContext $ctx): array
    {
        $invoices = ProjectInvoice::where('project_id', $ctx->project->id)
            ->where('type', 'client')
            ->orderByRaw('CAST(claim_number AS UNSIGNED)')
            ->orderBy('invoice_date')
            ->get();

        $rows = $invoices->map(function (ProjectInvoice $inv) {
            $certified = $inv->certified_current !== null
                ? (float) $inv->certified_current
                : ($inv->payment_cert_date !== null ? (float) $inv->amount : 0.0);

            return [
                'ipc_no' => $inv->claim_number,
                'submission_date' => $inv->invoice_date?->format('d/m/Y'),
                'evaluation_date' => $inv->evaluation_date?->format('d/m/Y'),
                'claim_amount' => (float) $inv->amount,
                'certified' => $certified,
                'wjp_current' => (float) ($inv->wjp_current ?? 0),
                'wjp_cumulative' => (float) ($inv->wjp_cumulative ?? 0),
                'paid_current' => (float) ($inv->certified_current ?? 0),
                'paid_cumulative' => (float) ($inv->certified_cumulative ?? 0),
                'remarks' => self::STATUS_LABELS[$inv->status] ?? $inv->status,
            ];
        })->values()->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
