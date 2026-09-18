@php
    $months = $data['series']['months'] ?? [];
    $scheduled = $data['series']['scheduled'] ?? [];
    $actual = $data['series']['actual'] ?? [];
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $chunks = array_chunk(array_keys($months), $isLandscape ? 13 : 12);
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
