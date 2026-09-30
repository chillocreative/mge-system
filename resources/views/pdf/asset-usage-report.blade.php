<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #1f2937; }
        h1 { margin: 0 0 4px; font-size: 16px; }
        .meta { margin-bottom: 12px; color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px; vertical-align: top; }
        th { background: #0f766e; color: white; text-align: left; }
        .number { text-align: right; }
    </style>
</head>
<body>
    <h1>Vehicle &amp; Machine Usage Report</h1>
    <div class="meta">Reporting month: {{ $report['month_label'] }}</div>
    <table>
        <thead><tr>
            <th>Asset</th><th>Category</th><th>Projects</th><th>Month Days</th><th>Total Days</th>
            <th>Maintenance</th><th>Maint. Due</th><th>Road Tax</th><th>Days Left</th>
        </tr></thead>
        <tbody>
        @forelse($report['rows'] as $row)
            <tr>
                <td>{{ $row['asset_no'] }}<br>{{ $row['asset'] }}</td>
                <td>{{ ucfirst($row['category']) }}</td>
                <td>{{ $row['projects'] ?: '-' }}</td>
                <td class="number">{{ $row['monthly_usage_days'] }}</td>
                <td class="number">{{ $row['total_usage_days'] }}</td>
                <td>{{ ucfirst(str_replace('_', ' ', $row['maintenance_status'])) }}</td>
                <td>{{ $row['maintenance_due_date'] ?: '-' }}</td>
                <td>{{ $row['road_tax_expiry_date'] ?: '-' }}<br>{{ ucfirst(str_replace('_', ' ', $row['road_tax_status'])) }}</td>
                <td class="number">{{ $row['road_tax_days_remaining'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No assets found.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
