<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Water Quality Monitoring Worksheet</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 9px; color: #000; line-height: 1.4; }
        .container { padding: 24px; }
        .header { display: table; width: 100%; margin-bottom: 12px; }
        .header-left { display: table-cell; width: 60px; vertical-align: top; }
        .header-left img { width: 50px; }
        .header-mid { display: table-cell; vertical-align: middle; text-align: center; }
        .title { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .meta-line { display: table; width: 100%; margin-bottom: 4px; }
        .meta-cell { display: table-cell; width: 50%; padding: 2px 6px; font-size: 10px; vertical-align: top; }
        .meta-cell strong { display: inline-block; min-width: 90px; }
        .project-name { font-size: 10px; margin: 6px 0 10px; }
        .project-name strong { display: block; margin-bottom: 2px; }
        .section-title { font-size: 11px; font-weight: bold; text-transform: uppercase; margin: 12px 0 4px; }
        table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; table-layout: fixed; }
        table.grid th, table.grid td { border: 1px solid #000; padding: 4px; vertical-align: top; font-size: 8.5px; text-align: left; }
        table.grid th { text-align: center; font-weight: bold; background: #f0f0f0; }
        table.grid td.point-cell { text-align: center; font-weight: bold; vertical-align: middle; }
        .chosen { font-weight: bold; text-decoration: underline; }
        table.insitu { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.insitu th, table.insitu td { border: 1px solid #000; padding: 5px; text-align: center; font-size: 9px; }
        table.insitu th { background: #f0f0f0; font-weight: bold; }
        table.insitu td.param-name { text-align: left; font-weight: bold; }
        .notes-line { margin-top: 12px; font-size: 10px; }
        .notes-rule { border-bottom: 1px solid #000; display: block; height: 16px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-left">
                @if($logo)<img src="{{ $logo }}">@endif
            </div>
            <div class="header-mid">
                <div class="title">Water Quality Monitoring Worksheet</div>
            </div>
            <div class="header-left"></div>
        </div>

        <div class="project-name">
            <strong>PROJECT'S NAME:</strong> {{ $project->name ?? '-' }}
        </div>

        <div class="meta-line">
            <div class="meta-cell"><strong>DATE:</strong> {{ optional($record->sample_date)->format('d/m/Y') ?? '-' }}</div>
            <div class="meta-cell"><strong>DATA COLLECTOR:</strong> {{ $record->data_collector ?: '-' }}</div>
        </div>
        <div class="meta-line">
            <div class="meta-cell"><strong>TIME:</strong> {{ $record->sample_time ? \Illuminate\Support\Carbon::parse($record->sample_time)->format('H:i') : '-' }}</div>
            <div class="meta-cell"><strong>WITNESS:</strong> {{ $record->witness ?: '-' }}</div>
        </div>

        <div class="section-title">River Conditions</div>
        @php
            $conditions = $record->conditions ?? [];
            $fields = ['odour' => 'Odour', 'floating' => 'Floating Material', 'area' => 'Description Area', 'flow' => 'Flow of Water', 'colour' => 'Colour', 'level' => 'Water Level', 'weather' => 'Weather'];
        @endphp
        <table class="grid">
            <thead>
                <tr>
                    <th style="width:5%">Point</th>
                    @foreach($fields as $label)
                        <th>{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($points as $point)
                    <tr>
                        <td class="point-cell">{{ $point }}</td>
                        @foreach($fields as $key => $label)
                            @php $selected = $conditions[$point][$key] ?? null; @endphp
                            <td>
                                @foreach($conditionOptions[$key] as $i => $option)
                                    @if($i > 0) / @endif
                                    <span @if($selected === $option) class="chosen" @endif>{{ $option }}</span>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="section-title">Parameter (In-Situ)</div>
        @php $insitu = $record->insitu ?? []; @endphp
        <table class="insitu">
            <thead>
                <tr>
                    <th rowspan="2" style="width:22%">Test Parameter</th>
                    <th colspan="2">Upstream</th>
                    <th colspan="2">Downstream</th>
                    <th rowspan="2" style="width:10%">Unit</th>
                </tr>
                <tr>
                    <th>W1</th>
                    <th>W2</th>
                    <th>W3</th>
                    <th>W4</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $rows = ['temperature' => 'Temperature', 'ph' => 'pH', 'do' => 'Dissolved Oxygen (D.O)'];
                @endphp
                @foreach($rows as $key => $label)
                    <tr>
                        <td class="param-name">{{ $label }}</td>
                        @foreach($points as $point)
                            <td>{{ $insitu[$point][$key] ?? '' }}</td>
                        @endforeach
                        <td>{{ $insituParams[$key] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="notes-line">
            <strong>NOTES/COMMENTS:</strong>
            <div class="notes-rule">{{ $record->notes }}</div>
            <div class="notes-rule"></div>
        </div>
    </div>
</body>
</html>
