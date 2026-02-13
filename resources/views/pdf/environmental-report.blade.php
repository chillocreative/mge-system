<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 30px; border-bottom: 3px solid #059669; padding-bottom: 16px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 22px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .company-subtitle { font-size: 10px; color: #334155; }
        .report-title { font-size: 20px; font-weight: bold; color: #1f2937; margin-bottom: 4px; }
        .report-type { font-size: 12px; color: #059669; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-satisfactory { background: #d1fae5; color: #065f46; }
        .badge-needs_improvement { background: #fef3c7; color: #92400e; }
        .badge-unsatisfactory { background: #fee2e2; color: #991b1b; }
        .badge-pending { background: #f3f4f6; color: #374151; }
        .badge-collected { background: #dbeafe; color: #1e40af; }
        .badge-disposed { background: #fef3c7; color: #92400e; }
        .badge-verified { background: #d1fae5; color: #065f46; }
        .badge-scheduled { background: #dbeafe; color: #1e40af; }
        .badge-in_progress { background: #fef3c7; color: #92400e; }
        .badge-completed { background: #d1fae5; color: #065f46; }
        .badge-closed { background: #f3f4f6; color: #374151; }
        .badge-hazardous { background: #fee2e2; color: #991b1b; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #059669; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1px solid #d1fae5; }
        .detail-grid { display: table; width: 100%; }
        .detail-row { display: table-row; }
        .detail-label { display: table-cell; width: 160px; padding: 6px 12px 6px 0; font-weight: bold; color: #6b7280; font-size: 11px; text-transform: uppercase; vertical-align: top; }
        .detail-value { display: table-cell; padding: 6px 0; font-size: 12px; color: #1f2937; vertical-align: top; }
        .text-block { background: #f0fdf4; border: 1px solid #d1fae5; border-radius: 6px; padding: 12px; margin-bottom: 12px; font-size: 12px; white-space: pre-wrap; }
        .footer { margin-top: 40px; text-align: center; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .footer p { font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">MGE-PMS</div>
                <div class="company-subtitle">Environmental Management Department</div>
            </div>
            <div class="header-right">
                <div class="report-type">{{ $type }}</div>
                <div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                    Generated: {{ now()->format('M d, Y h:i A') }}
                </div>
            </div>
        </div>

        {{-- ── Site Inspection Report ── --}}
        @if($record instanceof \App\Models\SiteInspection)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span class="badge badge-{{ $record->overall_status }}">{{ str_replace('_', ' ', ucfirst($record->overall_status)) }}</span>
            </div>

            <div class="section">
                <div class="section-title">Inspection Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Inspector</div><div class="detail-value">{{ $record->inspector->first_name }} {{ $record->inspector->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Inspection Date</div><div class="detail-value">{{ $record->inspection_date->format('M d, Y') }}</div></div>
                    <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value">{{ str_replace('_', ' ', ucfirst($record->type)) }}</div></div>
                    <div class="detail-row"><div class="detail-label">Follow-Up Required</div><div class="detail-value">{{ $record->follow_up_required ? 'Yes' : 'No' }}</div></div>
                    @if($record->follow_up_date)
                    <div class="detail-row"><div class="detail-label">Follow-Up Date</div><div class="detail-value">{{ $record->follow_up_date->format('M d, Y') }}</div></div>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-title">Findings</div>
                <div class="text-block">{{ $record->findings }}</div>
            </div>

            @if($record->recommendations)
            <div class="section">
                <div class="section-title">Recommendations</div>
                <div class="text-block">{{ $record->recommendations }}</div>
            </div>
            @endif

            @if($record->corrective_actions)
            <div class="section">
                <div class="section-title">Corrective Actions</div>
                <div class="text-block">{{ $record->corrective_actions }}</div>
            </div>
            @endif

        {{-- ── Environmental Audit Report ── --}}
        @elseif($record instanceof \App\Models\EnvironmentalAudit)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span class="badge badge-{{ $record->status }}">{{ str_replace('_', ' ', ucfirst($record->status)) }}</span>
            </div>

            <div class="section">
                <div class="section-title">Audit Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Auditor</div><div class="detail-value">{{ $record->auditor->first_name }} {{ $record->auditor->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Audit Date</div><div class="detail-value">{{ $record->audit_date->format('M d, Y') }}</div></div>
                    <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value">{{ ucfirst($record->type) }}</div></div>
                    @if($record->next_audit_date)
                    <div class="detail-row"><div class="detail-label">Next Audit Date</div><div class="detail-value">{{ $record->next_audit_date->format('M d, Y') }}</div></div>
                    @endif
                </div>
            </div>

            @if($record->scope)
            <div class="section">
                <div class="section-title">Scope</div>
                <div class="text-block">{{ $record->scope }}</div>
            </div>
            @endif

            <div class="section">
                <div class="section-title">Findings</div>
                <div class="text-block">{{ $record->findings }}</div>
            </div>

            @if($record->non_conformities)
            <div class="section">
                <div class="section-title">Non-Conformities</div>
                <div class="text-block">{{ $record->non_conformities }}</div>
            </div>
            @endif

            @if($record->corrective_actions)
            <div class="section">
                <div class="section-title">Corrective Actions</div>
                <div class="text-block">{{ $record->corrective_actions }}</div>
            </div>
            @endif
        @endif

        {{-- Photos section --}}
        @if($record->photos->count() > 0)
        <div class="section">
            <div class="section-title">Attached Photos ({{ $record->photos->count() }})</div>
            @foreach($record->photos as $photo)
            <div style="margin-bottom: 8px; font-size: 11px;">
                <strong>{{ $photo->file_name }}</strong>
                @if($photo->caption) — {{ $photo->caption }} @endif
            </div>
            @endforeach
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>This report was generated from the MGE-PMS Environmental Management System.</p>
            <p>MGE-PMS &mdash; Environmental Management Department</p>
        </div>
    </div>
</body>
</html>
