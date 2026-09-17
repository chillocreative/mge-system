@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small">
        <tr><th>No.</th><th>Ref. No.</th><th>Name</th><th>Date</th><th>Result</th><th>Remarks</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['ref_no'] ?? '' }}</td>
                <td>{{ $row['name'] ?? '' }}</td>
                <td>{{ $row['date'] ?? '-' }}</td>
                <td>{{ $row['result'] ?? '' }}</td>
                <td>{{ $row['remarks'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
