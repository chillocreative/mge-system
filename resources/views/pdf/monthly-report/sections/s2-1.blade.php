@if(empty($data['physical']['rows']) && empty($data['financial']['rows']))
    <p class="placeholder">No data.</p>
@else
    <p>Planning Days to Completion: {{ $data['planning_days'] ?? '-' }}</p>

    <div class="section-title" style="font-size:10px;">Physical Progress</div>
    <table class="grid">
        <tr><th>Item</th><th>{{ $data['physical']['prev_label'] ?? 'Previous' }}</th><th>{{ $data['physical']['cur_label'] ?? 'Current' }}</th></tr>
        @foreach(($data['physical']['rows'] ?? []) as $row)
            <tr class="avoid">
                <td>{{ $row['label'] ?? '' }}</td>
                <td>{{ $row['prev'] ?? '-' }}</td>
                <td>{{ $row['cur'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>

    <div class="section-title" style="font-size:10px;">Financial Progress</div>
    <table class="grid">
        <tr><th>Item</th><th>{{ $data['financial']['prev_label'] ?? 'Previous' }}</th><th>{{ $data['financial']['cur_label'] ?? 'Current' }}</th></tr>
        @foreach(($data['financial']['rows'] ?? []) as $row)
            <tr class="avoid">
                <td>{{ $row['label'] ?? '' }}</td>
                <td>{{ $row['prev'] ?? '-' }}</td>
                <td>{{ $row['cur'] ?? '-' }}</td>
            </tr>
        @endforeach
    </table>
@endif
