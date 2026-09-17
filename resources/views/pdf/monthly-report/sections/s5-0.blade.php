@php
    $hasAny = !empty($data['site_access']) || !empty($data['key_plan']) || !empty($data['pairs']);
@endphp
@if(!$hasAny)
    <p class="placeholder">No data.</p>
@else
    @if(!empty($data['site_access']))
        <p><strong>Site Access</strong></p>
        <div class="images">
            @foreach($data['site_access'] as $image)
                <div class="avoid">
                    @if(!empty($image['data_uri']))<img src="{{ $image['data_uri'] }}">@endif
                    <div class="caption">{{ $image['caption'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($data['key_plan']))
        <p><strong>Key Plan</strong></p>
        <div class="images">
            @foreach($data['key_plan'] as $image)
                <div class="avoid">
                    @if(!empty($image['data_uri']))<img src="{{ $image['data_uri'] }}">@endif
                    <div class="caption">{{ $image['caption'] ?? '' }}</div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($data['pairs']))
        <table class="grid">
            <tr><th style="width:50%">Previous</th><th style="width:50%">Current</th></tr>
            @foreach($data['pairs'] as $pair)
                <tr class="avoid">
                    <td>
                        <div><strong>{{ $pair['label'] ?? '' }}</strong></div>
                        @if(!empty($pair['previous']['data_uri']))
                            <img src="{{ $pair['previous']['data_uri'] }}" style="max-width:100%;max-height:80mm;">
                            <div class="caption">{{ $pair['previous']['caption'] ?? '' }}</div>
                        @else
                            <p class="placeholder">No image.</p>
                        @endif
                    </td>
                    <td>
                        <div><strong>{{ $pair['label'] ?? '' }}</strong></div>
                        @if(!empty($pair['current']['data_uri']))
                            <img src="{{ $pair['current']['data_uri'] }}" style="max-width:100%;max-height:80mm;">
                            <div class="caption">{{ $pair['current']['caption'] ?? '' }}</div>
                        @else
                            <p class="placeholder">No image.</p>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
@endif
