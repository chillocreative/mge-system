@if(!empty($data['gantt_pages']))
    <p>The work programme (Gantt chart) is attached on the following {{ $data['gantt_pages'] }} page(s).</p>
@endif

@if(empty($data['rows']))
    @if(empty($data['gantt_pages']))
        <p class="placeholder">No data.</p>
    @endif
@else
    <table class="grid small">
        <tr><th>No.</th><th>Task</th><th>Duration</th><th>Start</th><th>Finish</th><th>Actual</th><th>Plan</th></tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td>{{ $row['no'] ?? '' }}</td>
                <td>{{ $row['task'] ?? '' }}</td>
                <td>{{ $row['duration'] ?? '' }}</td>
                <td>{{ $row['start'] ?? '' }}</td>
                <td>{{ $row['finish'] ?? '' }}</td>
                <td>{{ $row['actual'] ?? '' }}</td>
                <td>{{ $row['plan'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif

@if(!empty($data['note']))
    <p class="note">{{ $data['note'] }}</p>
@endif
