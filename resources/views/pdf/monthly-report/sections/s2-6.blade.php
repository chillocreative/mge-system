@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small">
        <tr>
            <th>No.</th><th>Title</th><th>Issue</th><th>Reg. No.</th><th>Submitted</th>
            <th>Reply</th><th>Duration</th><th>Status</th><th>Impact</th>
        </tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['title'] ?? '' }}</td>
                <td>{{ $row['issue'] ?? '' }}</td>
                <td>{{ $row['reg_number'] ?? '' }}</td>
                <td>{!! nl2br(e($row['submitted'] ?? '-')) !!}</td>
                <td>{{ $row['reply'] ?? '-' }}</td>
                <td>{{ $row['duration'] ?? '' }}</td>
                <td>{{ $row['status'] ?? '' }}</td>
                <td>{{ $row['impact'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
