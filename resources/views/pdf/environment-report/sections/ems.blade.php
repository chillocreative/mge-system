@php
    $data = $sections['ems'];
@endphp
<div class="section-title">{{ $section_numbers['ems'] }} {{ $section_titles['ems'] }}</div>
<table class="kv">
    <tr><td class="label">Name Environment Consultant</td><td>{{ $data['consultant_name'] ?? 'TBA' }}</td></tr>
    <tr><td class="label">No. Registration</td><td>{{ $data['consultant_reg_no'] ?? 'TBA' }}</td></tr>
    <tr><td class="label">Name Environment Officer</td><td>{{ $data['officer_name'] ?? 'TBA' }}</td></tr>
    <tr><td class="label">No. Registration</td><td>{{ $data['officer_reg_no'] ?? 'TBA' }}</td></tr>
</table>

<div class="sub-title">2.1 Certified Environment Consultant</div>
<p>{{ $data['certified_text'] ?? '' }}</p>

@if($consultant_cert)
    <div class="center" style="margin-top:8px;">
        <img src="{{ $consultant_cert }}" style="max-width:100%;max-height:100mm;">
    </div>
@endif
