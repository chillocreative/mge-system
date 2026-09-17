@php
    $fmt = fn (int $minutes) => sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
    $rainingDays = collect($data['days'] ?? [])->filter(fn ($d) => !empty($d['intervals']));
@endphp
@if(empty($data['days']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small">
        <tr><th>Date</th><th>Rain Intervals</th><th>Hours</th></tr>
        @forelse($rainingDays as $day)
            @php
                $totalMinutes = 0;
                foreach ($day['intervals'] as $interval) { $totalMinutes += $interval[1] - $interval[0]; }
            @endphp
            <tr class="avoid">
                <td>{{ $day['date'] ?? '' }}</td>
                <td>
                    @foreach($day['intervals'] as $interval)
                        {{ $fmt($interval[0]) }} - {{ $fmt($interval[1]) }}@if(!$loop->last), @endif
                    @endforeach
                </td>
                <td>{{ round($totalMinutes / 60, 1) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No rain recorded this period.</td></tr>
        @endforelse
    </table>

    <p>
        Total Days: {{ $data['summary']['total_days'] ?? 0 }} &nbsp;|&nbsp;
        Raining Days: {{ $data['summary']['raining_days'] ?? 0 }} &nbsp;|&nbsp;
        Raining Hours: {{ $data['summary']['raining_hours'] ?? 0 }}
    </p>
@endif
