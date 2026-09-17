@if(empty($data['rows']))
    <p class="placeholder">No data.</p>
@else
    <table class="grid">
        @foreach($data['rows'] as $row)
            <tr class="avoid">
                <th style="width:30%">{{ $row['label'] ?? '' }}</th>
                <td>{!! nl2br(e($row['value'] ?? '-')) !!}</td>
            </tr>
        @endforeach
    </table>
@endif
