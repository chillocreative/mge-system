@if(empty($data['groups']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid">
        <tr>
            <th rowspan="2">Type</th>
            <th colspan="3">Accumulative</th>
            <th colspan="3">Previous ({{ $data['previous_label'] ?? '-' }})</th>
            <th colspan="3">Current ({{ $data['current_label'] ?? '-' }})</th>
        </tr>
        <tr>
            <th>Issued</th><th>Open</th><th>Closed</th>
            <th>Issued</th><th>Open</th><th>Closed</th>
            <th>Issued</th><th>Open</th><th>Closed</th>
        </tr>
        @foreach($data['groups'] as $group)
            <tr class="avoid">
                <td>{{ $group['label'] ?? $group['code'] ?? '' }}</td>
                <td>{{ $group['accumulative']['issued'] ?? 0 }}</td>
                <td>{{ $group['accumulative']['open'] ?? 0 }}</td>
                <td>{{ $group['accumulative']['closed'] ?? 0 }}</td>
                <td>{{ $group['previous']['issued'] ?? 0 }}</td>
                <td>{{ $group['previous']['open'] ?? 0 }}</td>
                <td>{{ $group['previous']['closed'] ?? 0 }}</td>
                <td>{{ $group['current']['issued'] ?? 0 }}</td>
                <td>{{ $group['current']['open'] ?? 0 }}</td>
                <td>{{ $group['current']['closed'] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>
@endif
