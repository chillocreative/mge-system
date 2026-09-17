@php
    $days = $data['days'] ?? [];
    $hours = range(0, 23);
    $filled = function (array $intervals, int $h): bool {
        foreach ($intervals as [$s, $e]) { if ($s < ($h + 1) * 60 && $e > $h * 60) return true; }
        return false;
    };
@endphp
@if(empty($days))
    <p class="placeholder">No data.</p>
@else
    <table class="weather-grid" style="table-layout:fixed; width:100%; border-collapse:collapse; font-size:6.5pt;">
        <thead><tr><th style="width:16mm">Date</th>@foreach($hours as $h)<th>{{ $h }}</th>@endforeach<th style="width:10mm">Hrs</th></tr></thead>
        <tbody>
        @foreach($days as $d)
            @php $hrs = round(array_sum(array_map(fn ($i) => max(0, min($i[1], 1440) - max($i[0], 0)), $d['intervals'] ?? [])) / 60, 1); @endphp
            <tr>
                <td>{{ \Carbon\Carbon::parse($d['date'])->format('d/m') }}</td>
                @foreach($hours as $h)<td style="{{ $filled($d['intervals'] ?? [], $h) ? 'background:#60a5fa;' : '' }}">&nbsp;</td>@endforeach
                <td style="text-align:right">{{ $hrs > 0 ? $hrs : '-' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table class="kv" style="margin-top:3mm">
        <tr><td>Total days</td><td>{{ $data['summary']['total_days'] ?? count($days) }}</td></tr>
        <tr><td>Raining days</td><td>{{ $data['summary']['raining_days'] ?? '-' }}</td></tr>
        <tr><td>Raining hours</td><td>{{ $data['summary']['raining_hours'] ?? '-' }}</td></tr>
    </table>
@endif
