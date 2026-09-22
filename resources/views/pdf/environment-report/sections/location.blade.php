<div class="section-title">{{ $section_numbers['location'] }} {{ $section_titles['location'] }}</div>
@if($location_image['data_uri'] ?? null)
    <div class="center">
        <img src="{{ $location_image['data_uri'] }}" style="max-width:100%;max-height:220mm;">
    </div>
@elseif($location_image['pdf_path'] ?? null)
    <p class="muted">Location map attached as PDF.</p>
@else
    <p class="muted">No location map has been provided.</p>
@endif
