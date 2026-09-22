@php
    $data = $sections['introduction'];
@endphp
<div class="section-title">{{ $section_numbers['introduction'] }} {{ $section_titles['introduction'] }}</div>
@foreach(explode("\n\n", (string) ($data['text'] ?? '')) as $para)
    <p style="margin-bottom:6px;">{{ $para }}</p>
@endforeach
