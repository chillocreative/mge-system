<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Site Log Monthly Report</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.6; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 20px; border-bottom: 3px solid #15803d; padding-bottom: 16px; }
        .header-left { display: table-cell; width: 70px; vertical-align: top; }
        .header-left img { width: 64px; }
        .header-mid { display: table-cell; vertical-align: top; }
        .header-right { display: table-cell; width: 34%; vertical-align: top; text-align: right; }
        .company-name { font-size: 16px; font-weight: bold; color: #15803d; }
        .company-muted { color: #6b7280; font-size: 10px; }
        .report-title { font-size: 18px; font-weight: bold; color: #1f2937; }
        .report-sub { font-size: 11px; color: #6b7280; margin-top: 4px; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 13px; font-weight: bold; text-transform: uppercase; color: #15803d; letter-spacing: 0.5px; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #dcfce7; }
        .stat-grid { display: table; width: 100%; margin-bottom: 8px; }
        .stat-cell { display: table-cell; width: 50%; padding: 10px 14px; background: #f0fdf4; border: 1px solid #dcfce7; }
        .stat-label { font-size: 10px; text-transform: uppercase; color: #6b7280; }
        .stat-value { font-size: 20px; font-weight: bold; color: #15803d; margin-top: 2px; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.items thead th { background: #15803d; color: #fff; padding: 7px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items tbody td { padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-size: 11px; vertical-align: top; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
        .footer { margin-top: 30px; text-align: center; padding-top: 14px; border-top: 1px solid #e5e7eb; }
        .footer p { font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                @if($logo)<img src="{{ $logo }}">@endif
            </div>
            <div class="header-mid">
                <div class="company-name">{{ $company['name'] ?? 'Multi Green Engineering Sdn. Bhd.' }}</div>
                <div class="company-muted">{{ $company['address'] ?? 'NO. 20 & 22, TINGKAT 4, JALAN KEKWA, 85000 SEGAMAT, JOHOR' }}</div>
            </div>
            <div class="header-right">
                <div class="report-title">Site Log Monthly Report</div>
                <div class="report-sub">{{ \Carbon\Carbon::parse($month . '-01')->format('F Y') }}</div>
                <div class="report-sub">Generated: {{ now()->format('M d, Y h:i A') }}</div>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Project</div>
            <div>{{ $project->name }} @if($project->code) ({{ $project->code }}) @endif</div>
        </div>

        <div class="section">
            <div class="section-title">Summary</div>
            <div class="stat-grid">
                <div class="stat-cell">
                    <div class="stat-label">Total Workers Logged (Man-Days)</div>
                    <div class="stat-value">{{ $totalWorkers }}</div>
                </div>
                <div class="stat-cell">
                    <div class="stat-label">Total Machinery Usage</div>
                    <div class="stat-value">{{ $machineryTotals->sum() }}</div>
                </div>
            </div>
        </div>

        @if($machineryTotals->count() > 0)
        <div class="section">
            <div class="section-title">Machinery Usage by Type</div>
            <table class="items">
                <thead><tr><th>Machinery Type</th><th style="text-align:right;">Total Quantity Used</th></tr></thead>
                <tbody>
                    @foreach($machineryTotals as $type => $qty)
                    <tr><td>{{ $type }}</td><td style="text-align:right;">{{ $qty }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="section">
            <div class="section-title">Daily Site Logs ({{ $logs->count() }})</div>
            <table class="items">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Weather</th>
                        <th>Weather Times</th>
                        <th style="text-align:right;">Workers</th>
                        <th>Machinery</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->log_date->format('d M Y') }}</td>
                        <td>{{ $log->weather ? ucfirst($log->weather) : '-' }}</td>
                        <td>{{ $log->weatherEvents->map(fn ($w) => ucfirst(str_replace('_', ' ', $w->condition)) . ' ' . $w->event_time->format('H:i'))->join(', ') ?: '-' }}</td>
                        <td style="text-align:right;">{{ $log->workers_count }}</td>
                        <td>{{ $log->machinery->map(fn ($m) => "{$m->machinery_type} x{$m->quantity}")->join(', ') ?: '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5">No site logs recorded for this month.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>This report was generated from the MGE-PMS Project Management System.</p>
            <p>{{ $company['name'] ?? 'Multi Green Engineering Sdn. Bhd.' }}</p>
        </div>
    </div>
</body>
</html>
