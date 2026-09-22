<div class="section-title">{{ $section_numbers['policy'] }} {{ $section_titles['policy'] }}</div>
@if($policy_image['data_uri'] ?? null)
    <div class="center">
        <img src="{{ $policy_image['data_uri'] }}" style="max-width:100%;max-height:220mm;">
    </div>
@elseif($policy_image['pdf_path'] ?? null)
    <p class="muted">Policy document attached as PDF.</p>
@else
    <p class="muted">No environmental policy document has been provided.</p>
@endif
