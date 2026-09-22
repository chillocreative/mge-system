@php
    $data = $sections['contract'];
@endphp
<div class="section-title">{{ $section_numbers['contract'] }} {{ $section_titles['contract'] }}</div>
<div class="sub-title">1.1 Contract Information</div>
<table class="kv">
    @foreach($data['rows'] ?? [] as $row)
        <tr>
            <td class="label">{{ $row['label'] }}</td>
            <td>
                @foreach(explode(' | ', (string) $row['value']) as $line)
                    {{ $line }}@if(! $loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
    @endforeach
</table>
