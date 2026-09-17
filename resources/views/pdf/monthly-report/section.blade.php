@php
    $partialView = 'pdf.monthly-report.sections.s'.str_replace('.', '-', $key);
@endphp
<div class="section">
    <div class="section-title">{{ $title }}</div>
    @if(!empty($data['placeholder']) || empty($data))
        <p class="placeholder">No data.</p>
    @else
        @include($partialView, ['data' => $data])
    @endif

    @if(!empty($note))
        <p class="note">{{ $note }}</p>
    @endif
</div>
