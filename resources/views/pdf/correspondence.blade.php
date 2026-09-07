<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Correspondence {{ $c->reference_no ?: $c->id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.55; }
        .container { padding: 40px; }
        .header { border-bottom: 3px solid #2563eb; padding-bottom: 14px; margin-bottom: 24px; }
        .company-name { font-size: 20px; font-weight: bold; color: #0f172a; }
        .doc-type { font-size: 12px; color: #2563eb; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; margin-top: 6px; }
        .title { font-size: 16px; font-weight: bold; margin-top: 4px; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 10px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .badge-open { background: #dbeafe; color: #1e40af; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-closed { background: #d1fae5; color: #065f46; }
        .badge-declined { background: #fee2e2; color: #991b1b; }
        .section { margin-bottom: 20px; }
        .section-title { font-size: 12px; font-weight: bold; text-transform: uppercase; color: #2563eb; letter-spacing: 0.5px; margin-bottom: 8px; padding-bottom: 3px; border-bottom: 1px solid #dbeafe; }
        .grid { display: table; width: 100%; }
        .row { display: table-row; }
        .label { display: table-cell; width: 150px; padding: 4px 12px 4px 0; font-weight: bold; color: #6b7280; font-size: 10px; text-transform: uppercase; vertical-align: top; }
        .value { display: table-cell; padding: 4px 0; font-size: 12px; vertical-align: top; }
        .text-block { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 10px; white-space: pre-wrap; }
        table.events { width: 100%; border-collapse: collapse; }
        table.events th { background: #f1f5f9; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #e2e8f0; }
        table.events td { padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .muted { color: #94a3b8; }
        ul.files { list-style: none; }
        ul.files li { padding: 3px 0; font-size: 11px; }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div class="company-name">MGE Engineering &amp; Construction</div>
        <div class="doc-type">{{ strtoupper($c->type) }} · {{ $c->reference_no ?: ('No. '.$c->id) }}</div>
        <div class="title">{{ $c->title }}</div>
    </div>

    <div class="section">
        <div class="section-title">Details</div>
        <div class="grid">
            <div class="row"><div class="label">Project</div><div class="value">{{ $c->project?->name }}{{ $c->project?->code ? ' ('.$c->project->code.')' : '' }}</div></div>
            <div class="row"><div class="label">Status</div><div class="value"><span class="badge badge-{{ $c->status }}">{{ $c->status }}</span></div></div>
            <div class="row"><div class="label">Currently at</div><div class="value">{{ $c->currentParty?->name ?? '—' }}</div></div>
            <div class="row"><div class="label">Raised</div><div class="value">{{ optional($c->raised_date)->format('d M Y') }} by {{ trim(($c->creator?->first_name ?? '').' '.($c->creator?->last_name ?? '')) ?: '—' }}</div></div>
            <div class="row"><div class="label">Due</div><div class="value">{{ optional($c->due_date)->format('d M Y') ?? '—' }}</div></div>
            <div class="row"><div class="label">Expected close</div><div class="value">{{ optional($c->expected_close_date)->format('d M Y') ?? '—' }}</div></div>
            <div class="row"><div class="label">Actual close</div><div class="value">{{ optional($c->actual_close_date)->format('d M Y') ?? '—' }}</div></div>
            @if ($c->closing_reference)
            <div class="row"><div class="label">Closing ref</div><div class="value">{{ $c->closing_reference }}</div></div>
            @endif
        </div>
    </div>

    @if ($c->description)
    <div class="section">
        <div class="section-title">Description</div>
        <div class="text-block">{{ $c->description }}</div>
    </div>
    @endif

    @if ($c->response)
    <div class="section">
        <div class="section-title">Response</div>
        <div class="text-block">{{ $c->response }}</div>
    </div>
    @endif

    <div class="section">
        <div class="section-title">History</div>
        <table class="events">
            <thead>
                <tr><th style="width:110px">Date</th><th style="width:110px">Event</th><th>Detail</th><th style="width:120px">By</th></tr>
            </thead>
            <tbody>
                @forelse ($c->events as $e)
                <tr>
                    <td>{{ $e->created_at?->format('d M Y H:i') }}</td>
                    <td>{{ str_replace('_', ' ', $e->event_type) }}</td>
                    <td>
                        @if ($e->event_type === 'handed_over')
                            {{ $e->fromParty?->name ?? '—' }} &rarr; {{ $e->toParty?->name ?? '—' }}
                        @elseif ($e->event_type === 'status_changed')
                            {{ $e->from_status ?? '—' }} &rarr; {{ $e->to_status ?? '—' }}
                        @endif
                        @if ($e->note)<div class="muted">{{ $e->note }}</div>@endif
                    </td>
                    <td>{{ trim(($e->creator?->first_name ?? '').' '.($e->creator?->last_name ?? '')) ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="muted">No history recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($c->files->count())
    <div class="section">
        <div class="section-title">Attachments</div>
        <ul class="files">
            @foreach ($c->files as $f)
            <li>• {{ $f->file_name ?? $f->original_name ?? 'file' }}</li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
</body>
</html>
