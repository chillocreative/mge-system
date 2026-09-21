<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Correspondence Register - {{ $project->name }} - {{ strtoupper($type->code) }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #1f2937; }
        .container { padding: 20px; }
        .header { display: table; width: 100%; border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 8px; }
        .header .logo-cell { display: table-cell; width: 100px; vertical-align: middle; }
        .header .logo-cell img { max-height: 50px; }
        .header .project-cell { display: table-cell; vertical-align: middle; text-align: center; }
        .project-cell .project-name { font-size: 16px; font-weight: bold; }
        .type-title { text-align: center; font-size: 13px; font-weight: bold; margin-bottom: 10px; }
        table.register { width: 100%; border-collapse: collapse; }
        table.register th, table.register td { border: 1px solid #000; padding: 4px 5px; font-size: 9px; vertical-align: top; }
        table.register thead th { background: #e5e7eb; text-align: center; font-weight: bold; }
        table.register tbody tr:nth-child(even) { background: #f9fafb; }
        .text-center { text-align: center; }
        .footer { margin-top: 14px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="logo-cell">
            @if ($logo ?? null)
                <img src="{{ $logo }}" alt="Logo">
            @endif
        </div>
        <div class="project-cell">
            <div class="project-name">PROJECT : {{ strtoupper($project->name) }}</div>
        </div>
    </div>

    <div class="type-title">{{ $type->full_name }} ({{ strtoupper($type->code) }})</div>

    <table class="register">
        <thead>
            <tr>
                <th rowspan="2">Bil</th>
                <th rowspan="2">Reference Number</th>
                <th rowspan="2">Title</th>
                <th rowspan="2">Date Issued</th>
                <th>Date</th>
                <th>Date</th>
                <th rowspan="2">Status</th>
                <th rowspan="2">REMARKS</th>
                <th rowspan="2">ATTACHMENT</th>
            </tr>
            <tr>
                <th>Inspection</th>
                <th>Closed</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td class="text-center">{{ $row['bil'] }}</td>
                    <td>{{ $row['reference_no'] }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td class="text-center">{{ $row['date_issued'] ? \Carbon\Carbon::parse($row['date_issued'])->format('d/m/Y') : '' }}</td>
                    <td class="text-center">{{ $row['date_inspection'] ? \Carbon\Carbon::parse($row['date_inspection'])->format('d/m/Y') : '' }}</td>
                    <td class="text-center">{{ $row['date_closed'] ? \Carbon\Carbon::parse($row['date_closed'])->format('d/m/Y') : '' }}</td>
                    <td class="text-center">{{ strtoupper($row['status'] ?? '') }}</td>
                    <td>{{ $row['remarks'] }}</td>
                    <td class="text-center">{{ $row['attachments'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">No correspondence records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generated {{ $generated_at->format('d/m/Y H:i') }} &middot; MGE-PMS</div>
</div>
</body>
</html>
