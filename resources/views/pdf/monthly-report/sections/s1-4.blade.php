@if(empty($data['tree']))
    <p class="placeholder">No data.</p>
@else
    <div>
        @include('pdf.monthly-report.sections.s1-4-node', ['nodes' => $data['tree'], 'depth' => 0])
    </div>
@endif

@if(!empty($data['note']))
    <p class="note">{{ $data['note'] }}</p>
@endif
