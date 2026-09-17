@php
    $hasAny = collect($data['groups'] ?? [])->contains(fn ($g) => !empty($g['rows']));
@endphp
@if(empty($data['groups']) || !$hasAny)
    <p class="placeholder">No data.</p>
@else
    @foreach($data['groups'] as $group)
        @continue(empty($group['rows']))
        <p><strong>{{ $group['label'] ?? $group['code'] ?? '' }}</strong> ({{ $group['ref_prefix'] ?? '' }})</p>
        <table class="grid small avoid">
            <tr><th>No.</th><th>Reference</th><th>Title</th><th>Issued</th><th>Approved</th><th>Reminder</th><th>Status</th></tr>
            @foreach($group['rows'] as $row)
                <tr class="avoid">
                    <td>{{ $row['no'] ?? '' }}</td>
                    <td>{{ $row['reference'] ?? '' }}</td>
                    <td>{{ $row['title'] ?? '' }}</td>
                    <td>{{ $row['issued'] ?? '-' }}</td>
                    <td>{{ $row['approved'] ?? '-' }}</td>
                    <td>{{ $row['reminder'] ?? '-' }}</td>
                    <td>{{ $row['status'] ?? '' }}</td>
                </tr>
            @endforeach
        </table>
    @endforeach
@endif
