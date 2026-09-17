@php
    $days = $data['days'] ?? [];
    $groups = $data['groups'] ?? [];
    $chunks = array_chunk(array_keys($days), 16);

    $totals = [];
    foreach (array_keys($days) as $i) {
        $sum = 0;
        foreach ($groups as $group) {
            foreach (($group['rows'] ?? []) as $row) {
                $sum += (float) ($row['counts'][$i] ?? 0);
            }
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
@if(empty($days) || empty($groups))
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
            @foreach($groups as $group)
                <tr><td colspan="{{ count($chunk) + 2 }}"><strong>{{ $group['label'] ?? '' }}</strong></td></tr>
                @foreach(($group['rows'] ?? []) as $row)
                    <tr class="avoid">
                        <td>{{ $no++ }}</td>
                        <td>{{ $row['description'] ?? '' }}</td>
                        @foreach($chunk as $i)<td>{{ $row['counts'][$i] ?? '-' }}</td>@endforeach
                    </tr>
                @endforeach
            @endforeach
            <tr class="avoid">
                <td colspan="2"><strong>Total</strong></td>
                @foreach($chunk as $i)<td><strong>{{ $totals[$i] ?? '-' }}</strong></td>@endforeach
            </tr>
        </table>
    @endforeach
@endif
