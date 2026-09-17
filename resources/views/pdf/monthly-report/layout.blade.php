<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $report->title }}</title>
<style>
    @if(($orientation ?? 'portrait') === 'landscape')
        @page { size: a4 landscape; margin: 22mm 12mm 18mm 12mm; }
    @else
        @page { margin: 26mm 14mm 18mm 14mm; }
    @endif
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
    table { border-collapse: collapse; }
    .header {
        position: fixed;
        top: -20mm;
        left: 0;
        right: 0;
        height: 16mm;
    }
    .header table { width: 100%; }
    .header td { vertical-align: middle; }
    .header .logo-cell { width: 20%; }
    .header .logo-cell img { max-height: 14mm; max-width: 100%; }
    .header .logo-cell.right { text-align: right; }
    .header .title-cell { width: 60%; text-align: center; }
    .header .title-cell .project-title { font-size: 10px; font-weight: bold; text-transform: uppercase; }
    .header .title-cell .contract-no { font-size: 9px; text-transform: uppercase; margin-top: 2px; }
    .header hr { border: none; border-bottom: 1px solid #111; margin-top: 3px; }

    .section { margin-bottom: 8px; }
    .section-title { font-weight: bold; text-transform: uppercase; font-size: 11px; margin: 6px 0 4px; }
    .note { font-style: italic; font-size: 9px; margin-top: 4px; }
    .placeholder { font-style: italic; color: #555; font-size: 9px; }
    .page-break { page-break-before: always; }
    .avoid { page-break-inside: avoid; }

    table.grid { width: 100%; margin-bottom: 6px; }
    table.grid th, table.grid td { border: 1px solid #111; padding: 3px 4px; font-size: 10px; vertical-align: top; text-align: left; }
    table.grid th { background-color: #d9ead3; font-weight: bold; }
    table.grid.small th, table.grid.small td { font-size: 7px; padding: 2px 3px; }

    table.weather-grid th, table.weather-grid td { border: 1px solid #ccc; padding: 1px; text-align: center; }
    table.weather-grid th { background-color: #d9ead3; font-weight: bold; }
    table.kv td { padding: 2px 6px; font-size: 9px; }
    table.kv td:first-child { font-weight: bold; }

    .images img { max-width: 100%; max-height: 110mm; display: block; margin: 0 auto 3px; }
    .images .caption { text-align: center; font-size: 9px; margin-bottom: 8px; }

    .signature-box { border: 1px solid #111; height: 22mm; margin-bottom: 3px; }
    .cover-title { text-align: center; font-size: 14px; font-weight: bold; text-transform: uppercase; margin: 10px 0; }
</style>
</head>
<body>

<div class="header">
    <table>
        <tr>
            <td class="logo-cell left">
                @if($logos['owner'] ?? null)
                    <img src="{{ $logos['owner'] }}" alt="Client logo">
                @endif
            </td>
            <td class="title-cell">
                <div class="project-title">{{ $ctx->project->name }}</div>
                <div class="contract-no">CONTRACT NO. : {{ $ctx->contract?->contract_no ?? $ctx->project->code }}</div>
            </td>
            <td class="logo-cell right">
                @if($mgeLogo)
                    <img src="{{ $mgeLogo }}" alt="MGE logo">
                @endif
            </td>
        </tr>
    </table>
    <hr>
</div>

@php
    $orderedKeys = $orderedKeys ?? array_keys(array_diff_key($sections, ['cover' => true]));
    $showCover = $showCover ?? true;
@endphp

@if($showCover)
    @include('pdf.monthly-report.cover', ['data' => $sections['cover'] ?? ['schema' => 1, 'placeholder' => true]])

    <div class="page-break"></div>
    <div class="section-title">Table of Contents</div>
    <table class="grid avoid">
        <tr><th>Section</th><th>Title</th></tr>
        @foreach($tocKeys ?? [] as $key)
            @continue($key === 'cover')
            <tr><td>{{ $key }}</td><td>{{ $titles[$key] ?? $key }}</td></tr>
        @endforeach
    </table>
@endif

@foreach($orderedKeys as $key)
    @continue(! isset($sections[$key]))
    @unless($loop->first && ! $showCover)
        <div class="page-break"></div>
    @endunless
    @include('pdf.monthly-report.section', ['key' => $key, 'title' => $titles[$key] ?? $key, 'data' => $sections[$key], 'note' => $notes[$key] ?? null, 'orientation' => $orientation ?? 'portrait'])
@endforeach

</body>
</html>
