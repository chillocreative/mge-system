@php
    $data = $sections['results'];
    $points = ['w1', 'w2', 'w3', 'w4'];
@endphp
<div class="section-title">{{ $section_numbers['results'] }} {{ $section_titles['results'] }}</div>

<table class="grid">
    <tr>
        <th rowspan="2" style="width:26%">Test Parameter</th>
        <th colspan="2">Specifications</th>
        <th colspan="4">Points</th>
    </tr>
    <tr>
        <th style="width:10%">NWQS</th>
        <th style="width:10%">DOE</th>
        @foreach($points as $point)
            <th style="width:9%">{{ strtoupper($point) }}</th>
        @endforeach
    </tr>
    @foreach(($data['rows'] ?? []) as $row)
        <tr>
            <td>{{ $row['parameter'] }}</td>
            <td class="center">{{ $row['nwqs'] ?? '-' }}</td>
            <td class="center">{{ $row['doe'] ?? '-' }}</td>
            @foreach($points as $point)
                <td class="center">{{ $row[$point] ?? '-' }}</td>
            @endforeach
        </tr>
    @endforeach
</table>
