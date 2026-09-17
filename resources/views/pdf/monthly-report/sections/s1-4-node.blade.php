@foreach($nodes as $node)
    <div style="margin-left: {{ $depth * 12 }}mm; font-size: 9px; padding: 1px 0;">
        &bull; <strong>{{ $node['name'] ?? '' }}</strong>
        @if(!empty($node['designation'])) &mdash; {{ $node['designation'] }}@endif
    </div>
    @if(!empty($node['children']))
        @include('pdf.monthly-report.sections.s1-4-node', ['nodes' => $node['children'], 'depth' => $depth + 1])
    @endif
@endforeach
