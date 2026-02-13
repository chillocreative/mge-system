<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $type }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 30px; border-bottom: 3px solid #dc2626; padding-bottom: 16px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 22px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .company-subtitle { font-size: 10px; color: #334155; }
        .report-title { font-size: 20px; font-weight: bold; color: #1f2937; margin-bottom: 4px; }
        .report-type { font-size: 12px; color: #dc2626; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .badge { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-open { background: #fee2e2; color: #991b1b; }
        .badge-investigating { background: #fef3c7; color: #92400e; }
        .badge-resolved, .badge-closed, .badge-compliant { background: #d1fae5; color: #065f46; }
        .badge-minor { background: #dbeafe; color: #1e40af; }
        .badge-moderate { background: #fef3c7; color: #92400e; }
        .badge-serious { background: #fed7aa; color: #9a3412; }
        .badge-critical { background: #fee2e2; color: #991b1b; }
        .badge-low { background: #d1fae5; color: #065f46; }
        .badge-medium { background: #fef3c7; color: #92400e; }
        .badge-high { background: #fed7aa; color: #9a3412; }
        .badge-non_compliant { background: #fee2e2; color: #991b1b; }
        .badge-partial { background: #fef3c7; color: #92400e; }
        .badge-pass { background: #d1fae5; color: #065f46; }
        .badge-fail { background: #fee2e2; color: #991b1b; }
        .badge-na { background: #f3f4f6; color: #6b7280; }
        .section { margin-bottom: 24px; }
        .section-title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #dc2626; letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 4px; border-bottom: 1px solid #fee2e2; }
        .detail-grid { display: table; width: 100%; }
        .detail-row { display: table-row; }
        .detail-label { display: table-cell; width: 160px; padding: 6px 12px 6px 0; font-weight: bold; color: #6b7280; font-size: 11px; text-transform: uppercase; vertical-align: top; }
        .detail-value { display: table-cell; padding: 6px 0; font-size: 12px; color: #1f2937; vertical-align: top; }
        .text-block { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; margin-bottom: 12px; font-size: 12px; white-space: pre-wrap; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items thead th { background: #dc2626; color: #fff; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items tbody td { padding: 8px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
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
                <div class="company-subtitle">Safety & Compliance Department</div>
            </div>
            <div class="header-right">
                <div class="report-type">{{ $type }}</div>
                <div style="margin-top: 8px; font-size: 11px; color: #6b7280;">
                    Generated: {{ now()->format('M d, Y h:i A') }}
                </div>
            </div>
        </div>

        {{-- ── Incident Report ── --}}
        @if($record instanceof \App\Models\SafetyIncident)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span class="badge badge-{{ $record->severity }}">{{ ucfirst($record->severity) }}</span>
                <span class="badge badge-{{ $record->status }}" style="margin-left: 4px;">{{ ucfirst($record->status) }}</span>
            </div>

            <div class="section">
                <div class="section-title">Incident Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Reported By</div><div class="detail-value">{{ $record->reporter->first_name }} {{ $record->reporter->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Date & Time</div><div class="detail-value">{{ $record->incident_date->format('M d, Y') }} {{ $record->incident_time ? '@ ' . $record->incident_time : '' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">{{ $record->location ?: 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value">{{ str_replace('_', ' ', ucfirst($record->type)) }}</div></div>
                    @if($record->injured_person)
                    <div class="detail-row"><div class="detail-label">Injured Person</div><div class="detail-value">{{ $record->injured_person }}</div></div>
                    @endif
                    @if($record->investigator)
                    <div class="detail-row"><div class="detail-label">Investigated By</div><div class="detail-value">{{ $record->investigator->first_name }} {{ $record->investigator->last_name }}</div></div>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-title">Description</div>
                <div class="text-block">{{ $record->description }}</div>
            </div>

            @if($record->injury_description)
            <div class="section">
                <div class="section-title">Injury Description</div>
                <div class="text-block">{{ $record->injury_description }}</div>
            </div>
            @endif

            @if($record->root_cause)
            <div class="section">
                <div class="section-title">Root Cause</div>
                <div class="text-block">{{ $record->root_cause }}</div>
            </div>
            @endif

            @if($record->corrective_action)
            <div class="section">
                <div class="section-title">Corrective Action</div>
                <div class="text-block">{{ $record->corrective_action }}</div>
            </div>
            @endif

            @if($record->preventive_action)
            <div class="section">
                <div class="section-title">Preventive Action</div>
                <div class="text-block">{{ $record->preventive_action }}</div>
            </div>
            @endif

        {{-- ── Hazard Report ── --}}
        @elseif($record instanceof \App\Models\HazardReport)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span class="badge badge-{{ $record->risk_level }}">{{ ucfirst($record->risk_level) }} Risk</span>
                <span class="badge badge-{{ $record->status }}" style="margin-left: 4px;">{{ ucfirst($record->status) }}</span>
            </div>

            <div class="section">
                <div class="section-title">Hazard Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Reported By</div><div class="detail-value">{{ $record->reporter->first_name }} {{ $record->reporter->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Date Reported</div><div class="detail-value">{{ $record->created_at->format('M d, Y') }}</div></div>
                    <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">{{ $record->location ?: 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Hazard Type</div><div class="detail-value">{{ str_replace('_', ' ', ucfirst($record->hazard_type)) }}</div></div>
                    @if($record->assignee)
                    <div class="detail-row"><div class="detail-label">Assigned To</div><div class="detail-value">{{ $record->assignee->first_name }} {{ $record->assignee->last_name }}</div></div>
                    @endif
                </div>
            </div>

            <div class="section">
                <div class="section-title">Description</div>
                <div class="text-block">{{ $record->description }}</div>
            </div>

            @if($record->recommended_action)
            <div class="section">
                <div class="section-title">Recommended Action</div>
                <div class="text-block">{{ $record->recommended_action }}</div>
            </div>
            @endif

            @if($record->corrective_action)
            <div class="section">
                <div class="section-title">Corrective Action Taken</div>
                <div class="text-block">{{ $record->corrective_action }}</div>
            </div>
            @endif

        {{-- ── Toolbox Meeting ── --}}
        @elseif($record instanceof \App\Models\ToolboxMeeting)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span style="font-size: 11px; color: #6b7280;">Meeting Date: {{ $record->meeting_date->format('M d, Y') }}</span>
            </div>

            <div class="section">
                <div class="section-title">Meeting Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Conducted By</div><div class="detail-value">{{ $record->conductor->first_name }} {{ $record->conductor->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Location</div><div class="detail-value">{{ $record->location ?: 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Duration</div><div class="detail-value">{{ $record->duration_minutes ? $record->duration_minutes . ' minutes' : 'N/A' }}</div></div>
                </div>
            </div>

            <div class="section">
                <div class="section-title">Topics Discussed</div>
                <div class="text-block">{{ $record->topics }}</div>
            </div>

            @if($record->notes)
            <div class="section">
                <div class="section-title">Notes</div>
                <div class="text-block">{{ $record->notes }}</div>
            </div>
            @endif

            @if($record->action_items)
            <div class="section">
                <div class="section-title">Action Items</div>
                <div class="text-block">{{ $record->action_items }}</div>
            </div>
            @endif

            @if($record->attendees->count() > 0)
            <div class="section">
                <div class="section-title">Attendees ({{ $record->attendees->count() }})</div>
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 10%;">#</th>
                            <th style="width: 50%;">Name</th>
                            <th style="width: 40%;">Type</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($record->attendees as $i => $att)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $att->display_name }}</td>
                            <td>{{ $att->user_id ? 'Employee' : 'External' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

        {{-- ── Compliance Checklist ── --}}
        @elseif($record instanceof \App\Models\ComplianceChecklist)
            <div class="report-title">{{ $record->title }}</div>
            <div style="margin: 8px 0 24px;">
                <span class="badge badge-{{ $record->overall_status }}">{{ str_replace('_', ' ', ucfirst($record->overall_status)) }}</span>
            </div>

            <div class="section">
                <div class="section-title">Checklist Details</div>
                <div class="detail-grid">
                    <div class="detail-row"><div class="detail-label">Project</div><div class="detail-value">{{ $record->project->name ?? 'N/A' }}</div></div>
                    <div class="detail-row"><div class="detail-label">Inspector</div><div class="detail-value">{{ $record->inspector->first_name }} {{ $record->inspector->last_name }}</div></div>
                    <div class="detail-row"><div class="detail-label">Date</div><div class="detail-value">{{ $record->checklist_date->format('M d, Y') }}</div></div>
                    <div class="detail-row"><div class="detail-label">Type</div><div class="detail-value">{{ str_replace('_', ' ', strtoupper($record->type)) }}</div></div>
                </div>
            </div>

            @if($record->items->count() > 0)
            <div class="section">
                <div class="section-title">Checklist Items</div>
                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 5%;">#</th>
                            <th style="width: 55%;">Item</th>
                            <th style="width: 15%;">Status</th>
                            <th style="width: 25%;">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($record->items as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>{{ $item->item_text }}</td>
                            <td><span class="badge badge-{{ $item->status }}">{{ strtoupper($item->status) }}</span></td>
                            <td>{{ $item->notes ?: '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if($record->notes)
            <div class="section">
                <div class="section-title">Additional Notes</div>
                <div class="text-block">{{ $record->notes }}</div>
            </div>
            @endif
        @endif

        {{-- Photos section for all types --}}
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
            <p>This report was generated from the MGE-PMS Safety Management System.</p>
            <p>MGE-PMS &mdash; Safety & Compliance Department</p>
        </div>
    </div>
</body>
</html>
