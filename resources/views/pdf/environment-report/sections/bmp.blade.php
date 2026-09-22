@php
    $data = $sections['bmp'];
@endphp
<div class="section-title">{{ $section_numbers['bmp'] }} {{ $section_titles['bmp'] }}</div>
<p>{{ $data['intro'] ?? '' }}</p>

<table class="grid avoid">
    <tr>
        <th style="width:8%">No.</th>
        <th>Items</th>
        <th style="width:14%">(&radic;)</th>
    </tr>
    @foreach(($data['items'] ?? []) as $item)
        <tr>
            <td class="center">{{ $item['no'] }}</td>
            <td>{{ $item['item'] }}</td>
            <td class="bmp-check">{!! ! empty($item['installed']) ? '&radic;' : '' !!}</td>
        </tr>
    @endforeach
</table>

@if(! empty($bmp_photos))
    <div class="sub-title">Photographs</div>
    <table class="photo-grid">
        @foreach(array_chunk($bmp_photos, 2) as $row)
            <tr>
                @foreach($row as $photo)
                    <td>
                        <img src="{{ $photo['data_uri'] }}">
                        @if($photo['caption'])
                            <div class="photo-caption">{{ $photo['caption'] }}</div>
                        @endif
                    </td>
                @endforeach
                @if(count($row) === 1)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>
@endif
