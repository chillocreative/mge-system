@php
    $days = $data['days'] ?? [];
    $rows = $data['rows'] ?? [];
    $totals = $data['totals'] ?? [];
    $chunks = array_chunk(array_keys($days), 16);

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
        @php $no = 1; @endphp
        <table class="grid small avoid">
            <tr>
                <th rowspan="2">No.</th><th rowspan="2">Description</th>
                @foreach($monthRunsFor($chunk) as $run)<th colspan="{{ $run['span'] }}">{{ $run['month'] }}</th>@endforeach
            </tr>
            <tr>
                @foreach($chunk as $i)<th>{{ $days[$i]['label'] ?? $days[$i]['date'] ?? '' }}</th>@endforeach
            </tr>
            @foreach($rows as $row)
                <tr class="avoid">
                    <td>{{ $no++ }}</td>
                    <td>{{ $row['description'] ?? '' }}</td>
                    @foreach($chunk as $i)<td>{{ $row['counts'][$i] ?? '-' }}</td>@endforeach
                </tr>
            @endforeach
            <tr class="avoid">
                <td colspan="2"><strong>Total</strong></td>
                @foreach($chunk as $i)<td><strong>{{ $totals[$i] ?? '-' }}</strong></td>@endforeach
            </tr>
        </table>
    @endforeach
@endif
