@php
    $months = $data['series']['months'] ?? [];
    $scheduled = $data['series']['scheduled'] ?? [];
    $actual = $data['series']['actual'] ?? [];
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
                <td>Scheduled (%)</td>
                @foreach($chunk as $i)<td>{{ $scheduled[$i] ?? '-' }}</td>@endforeach
            </tr>
            <tr>
                <td>Actual (%)</td>
                @foreach($chunk as $i)<td>{{ $actual[$i] ?? '-' }}</td>@endforeach
            </tr>
        </table>
    @endforeach
@endif
<p class="note">Chart available in a later phase.</p>
