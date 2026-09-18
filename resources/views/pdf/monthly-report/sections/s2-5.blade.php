@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $fontSize = $isLandscape ? '6.5pt' : '8pt';
    $version = $data['version'] ?? null;
@endphp

@if($version)
    <p>Programme: {{ $version['label'] ?? '' }}@if(!empty($version['status_date'])) (status date {{ $version['status_date'] }})@endif</p>
@endif

@if(empty($data['rows']))
    @if(empty($data['attached_pages']))
        <p class="placeholder">No data.</p>
    @endif
@else
    <table class="grid small" style="page-break-inside: auto;">
        <thead>
            <tr>
                <th style="font-size: {{ $fontSize }};">No.</th>
                <th style="font-size: {{ $fontSize }};">Task</th>
                <th style="font-size: {{ $fontSize }};">Duration</th>
                <th style="font-size: {{ $fontSize }};">Start</th>
                <th style="font-size: {{ $fontSize }};">Finish</th>
                <th style="font-size: {{ $fontSize }};">Actual %</th>
                <th style="font-size: {{ $fontSize }};">Plan %</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['rows'] as $row)
                @php
                    $level = (int) ($row['level'] ?? 1);
                    $indentStyle = $level > 1 ? ' padding-left: '.(($level - 1) * 3).'mm;' : '';
                    $isSummary = ! empty($row['summary']);
                @endphp
                <tr style="page-break-inside: avoid;">
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['no'] ?? '').'</strong>' : e($row['no'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};{{ $indentStyle }}">{!! $isSummary ? '<strong>'.e($row['task'] ?? '').'</strong>' : e($row['task'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['duration'] ?? '').'</strong>' : e($row['duration'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['start'] ?? '').'</strong>' : e($row['start'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['finish'] ?? '').'</strong>' : e($row['finish'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['actual'] ?? '').'</strong>' : e($row['actual'] ?? '') !!}</td>
                    <td style="font-size: {{ $fontSize }};">{!! $isSummary ? '<strong>'.e($row['plan'] ?? '').'</strong>' : e($row['plan'] ?? '') !!}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if(!empty($data['attached_pages']))
    <p>The work programme (Gantt chart) is attached on the following {{ $data['attached_pages'] }} page(s).</p>
@endif

@if(!empty($data['note']))
    <p class="note">{{ $data['note'] }}</p>
@endif
