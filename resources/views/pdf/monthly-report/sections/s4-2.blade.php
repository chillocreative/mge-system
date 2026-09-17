@php
    $days = $data['days'] ?? [];
    $rows = $data['rows'] ?? [];
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $chunks = $isLandscape ? [array_keys($days)] : array_chunk(array_keys($days), 16);
    $cellStyle = $isLandscape ? 'font-size:5.5pt; white-space:nowrap;' : '';

    $totals = [];
    foreach (array_keys($days) as $i) {
        $sum = 0;
        foreach ($rows as $row) {
            $sum += (float) ($row['counts'][$i] ?? 0);
        }
        $totals[$i] = $sum == (int) $sum ? (int) $sum : $sum;
    }

    $monthRunsFor = function (array $chunk) use ($days) {
        $runs = [];
        $currentMonth = null;
        $span = 0;
        foreach ($chunk as $i) {
            $month = $days[$i]['month'] ?? '';
            if ($month === $currentMonth) {
                $span++;
                continue;
            }
            if ($currentMonth !== null) {
                $runs[] = ['month' => $currentMonth, 'span' => $span];
            }
            $currentMonth = $month;
            $span = 1;
        }
        if ($currentMonth !== null) {
            $runs[] = ['month' => $currentMonth, 'span' => $span];
        }

        return $runs;
    };
@endphp
@if(empty($days) || empty($rows))
    <p class="placeholder">No data.</p>
@else
    @foreach($chunks as $chunk)
        @php
            $no = 1;
            // Fixed table-layout needs explicit column widths, or the Description column
            // collapses and its text overlaps the day cells. 8mm (No.) + 42mm (Description),
            // the remaining ~223mm of the ~273mm landscape printable width split across days.
            $dayWidth = $isLandscape && count($chunk) > 0 ? (273 - 50) / count($chunk) : 0;
        @endphp
        <table class="grid small avoid" @if($isLandscape) style="table-layout:fixed;" @endif>
            @if($isLandscape)
                <colgroup>
                    <col style="width:8mm;">
                    <col style="width:42mm;">
                    @foreach($chunk as $i)<col style="width:{{ $dayWidth }}mm;">@endforeach
                </colgroup>
            @endif
            <tr>
                <th rowspan="2" style="{{ $cellStyle }}">No.</th><th rowspan="2" style="{{ $cellStyle }}">Description</th>
                @foreach($monthRunsFor($chunk) as $run)<th colspan="{{ $run['span'] }}" style="{{ $cellStyle }}">{{ $run['month'] }}</th>@endforeach
            </tr>
            <tr>
                @foreach($chunk as $i)<th style="{{ $cellStyle }}">{{ $days[$i]['label'] ?? $days[$i]['date'] ?? '' }}</th>@endforeach
            </tr>
            @foreach($rows as $row)
                <tr class="avoid">
                    <td style="{{ $cellStyle }}">{{ $no++ }}</td>
                    <td style="{{ $cellStyle }}">{{ $row['description'] ?? '' }}</td>
                    @foreach($chunk as $i)<td style="{{ $cellStyle }}">{{ $row['counts'][$i] ?? '-' }}</td>@endforeach
                </tr>
            @endforeach
            <tr class="avoid">
                <td colspan="2" style="{{ $cellStyle }}"><strong>Total</strong></td>
                @foreach($chunk as $i)<td style="{{ $cellStyle }}"><strong>{{ empty($totals[$i]) ? '-' : $totals[$i] }}</strong></td>@endforeach
            </tr>
        </table>
    @endforeach
@endif
