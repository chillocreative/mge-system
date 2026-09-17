@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small">
        <tr><th>No.</th><th>Description</th><th>Date</th><th>Location</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['description'] ?? '' }}</td>
                <td>{{ $row['date'] ?? '-' }}</td>
                <td>{{ $row['location'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
