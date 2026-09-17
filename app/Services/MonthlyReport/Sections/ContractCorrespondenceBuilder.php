<?php

namespace App\Services\MonthlyReport\Sections;

use App\Models\ProjectParty;
use App\Services\MonthlyReport\ReportContext;

final class ContractCorrespondenceBuilder extends AbstractBuilder
{
    public const ROLE_LABELS = [
        'owner' => 'Project Owner', 'superintending_officer' => 'Superintendent Officer (SO)', 'so_representative' => 'Representative of the Principal Superintending Officer',
        'district_engineer' => 'District Engineer', 'quantity_surveyor' => 'Quantity Surveyor', 'consultant' => 'Consultant', 'contractor' => 'Contractor',
    ];

    public function build(ReportContext $ctx): array
    {
        $rows = $ctx->parties->map(fn (ProjectParty $p) => [
            'party' => $p->report_role === 'other' ? ($p->role_label ?: 'Other') : (self::ROLE_LABELS[$p->report_role] ?? $p->report_role),
            'company' => $p->name,
            'address' => $p->address,
            'contacts' => $p->contacts->map(fn ($c) => ['name' => $c->name, 'designation' => $c->designation, 'tel' => $c->phone, 'email' => $c->email])->values()->all(),
        ])->values()->all();

        return ['schema' => 1, 'rows' => $rows];
    }
}
