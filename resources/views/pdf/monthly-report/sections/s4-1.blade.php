@php
    $days = $data['days'] ?? [];
    $groups = $data['groups'] ?? [];
    $totals = $data['totals'] ?? [];
    $chunks = array_chunk(array_keys($days), 16);
@endphp
@if(empty($days) || empty($groups))
    <p class="placeholder">No data.</p>
@else
    @foreach($chunks as $chunk)
        <table class="grid small avoid">
            <tr>
                <th>No.</th><th>Description</th>
                @foreach($chunk as $i)<th>{{ $days[$i]['label'] ?? $days[$i]['date'] ?? '' }}</th>@endforeach
            </tr>
            @foreach($groups as $group)
                <tr><td colspan="{{ count($chunk) + 2 }}"><strong>{{ $group['label'] ?? '' }}</strong></td></tr>
                @foreach(($group['rows'] ?? []) as $row)
                    <tr class="avoid">
                        <td>{{ $row['no'] ?? '' }}</td>
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
