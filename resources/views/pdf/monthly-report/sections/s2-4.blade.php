@php
    $months = $data['series']['months'] ?? [];
    $scheduled = $data['series']['scheduled'] ?? [];
    $scheduledPct = $data['series']['scheduled_pct'] ?? [];
    $actual = $data['series']['actual'] ?? [];
    $actualPct = $data['series']['actual_pct'] ?? [];
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $chunks = array_chunk(array_keys($months), $isLandscape ? 13 : 6);
@endphp
@if(empty($months))
    <p class="placeholder">No data.</p>
@else
    @if(!empty($data['attached_pages']))
        <p>The S-curve chart is attached on the following {{ $data['attached_pages'] }} page(s).</p>
    @elseif(!empty($data['chart_svg_uri']))
        <img src="{{ $data['chart_svg_uri'] }}" style="width: {{ $isLandscape ? '88%' : '100%' }}; height: auto; display:block; margin: 0 auto 4mm auto;">
    @endif
    @foreach($chunks as $chunk)
        <table class="grid small avoid" style="table-layout: fixed; width: 100%; word-wrap: break-word;">
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
