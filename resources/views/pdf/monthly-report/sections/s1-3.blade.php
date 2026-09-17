@if(empty($data['images']))
    <p class="placeholder">No data.</p>
@else
    <div class="images">
        @foreach($data['images'] as $image)
            <div class="avoid">
                @if(!empty($image['data_uri']))
                    <img src="{{ $image['data_uri'] }}">
                @endif
                <div class="caption">{{ $image['caption'] ?? '' }}</div>
            </div>
        @endforeach
    </div>
@endif
