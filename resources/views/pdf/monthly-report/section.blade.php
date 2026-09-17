@php
    $partialView = 'pdf.monthly-report.sections.s'.str_replace('.', '-', $key);
@endphp
<div class="section">
    <div class="section-title">{{ $title }}</div>
    @if(!empty($data['placeholder']) || empty($data))
        <p class="placeholder">No data.</p>
    @else
        @php
            try {
                echo view($partialView, ['data' => $data])->render();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Monthly report section failed to render', ['key' => $key, 'error' => $e->getMessage()]);
                echo '<p class="placeholder">Section could not be rendered.</p>';
            }
        @endphp
    @endif

    @if(!empty($note))
        <p class="note">{{ $note }}</p>
    @endif
</div>
