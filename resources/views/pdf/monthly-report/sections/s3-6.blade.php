@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small">
        <tr><th>No.</th><th>Drawing No.</th><th>Title</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['drawing_no'] ?? '' }}</td>
                <td>{{ $row['title'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
