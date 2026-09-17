@php
    $isLandscape = ($orientation ?? 'portrait') === 'landscape';
    $tableStyle = $isLandscape ? 'font-size:7pt;' : '';
    $numStyle = $isLandscape ? 'white-space:nowrap;' : '';
@endphp
@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid small" @if($isLandscape) style="{{ $tableStyle }}" @endif>
        <tr>
            <th>IPC No.</th><th>Submission</th><th>Evaluation</th><th>Claim Amount</th><th>Certified</th>
            <th>WJP Current</th><th>WJP Cumulative</th><th>Paid Current</th><th>Paid Cumulative</th><th>Remarks</th>
        </tr>
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <td style="{{ $numStyle }}">{{ $row['ipc_no'] ?? '' }}</td>
                <td style="{{ $numStyle }}">{{ $row['submission_date'] ?? '-' }}</td>
                <td style="{{ $numStyle }}">{{ $row['evaluation_date'] ?? '-' }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['claim_amount'] ?? 0, 2) }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['certified'] ?? 0, 2) }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['wjp_current'] ?? 0, 2) }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['wjp_cumulative'] ?? 0, 2) }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['paid_current'] ?? 0, 2) }}</td>
                <td style="{{ $numStyle }}">{{ number_format($row['paid_cumulative'] ?? 0, 2) }}</td>
                <td>{{ $row['remarks'] ?? '' }}</td>
            </tr>
        @endforeach
    </table>
@endif
