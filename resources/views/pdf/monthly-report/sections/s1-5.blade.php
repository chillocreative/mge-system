@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <p>Company: {{ $data['company'] ?? '-' }}</p>
    <table class="grid">
        <tr><th>Designation</th><th>Nos.</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['designation'] ?? '' }}</td>
                <td>{{ $row['nos'] ?? 0 }}</td>
            </tr>
        @endforeach
    </table>
@endif
