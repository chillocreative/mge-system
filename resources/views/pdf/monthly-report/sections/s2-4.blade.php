@php
    $months = $data['series']['months'] ?? [];
    $scheduled = $data['series']['scheduled'] ?? [];
    $scheduledPct = $data['series']['scheduled_pct'] ?? [];
    $actual = $data['series']['actual'] ?? [];
    $actualPct = $data['series']['actual_pct'] ?? [];
    $chunks = array_chunk(array_keys($months), 12);
@endphp
@if(empty($months))
    <p class="placeholder">No data.</p>
@else
    @foreach($chunks as $chunk)
        <table class="grid small avoid">
            <tr>
                <th>Month</th>
                @foreach($chunk as $i)<th>{{ $months[$i] }}</th>@endforeach
            </tr>
            <tr>
                <td>Scheduled (RM)</td>
                @foreach($chunk as $i)<td>{{ isset($scheduled[$i]) ? number_format($scheduled[$i], 2) : '-' }}</td>@endforeach
            </tr>
            <tr>
                <td>Scheduled (%)</td>
                @foreach($chunk as $i)<td>{{ $scheduledPct[$i] ?? '-' }}</td>@endforeach
            </tr>
            <tr>
                <td>Actual (RM)</td>
                @foreach($chunk as $i)<td>{{ isset($actual[$i]) && $actual[$i] !== null ? number_format($actual[$i], 2) : '-' }}</td>@endforeach
            </tr>
            <tr>
                <td>Actual (%)</td>
                @foreach($chunk as $i)<td>{{ $actualPct[$i] ?? '-' }}</td>@endforeach
            </tr>
        </table>
    @endforeach
@endif
<p class="note">Chart available in a later phase.</p>
