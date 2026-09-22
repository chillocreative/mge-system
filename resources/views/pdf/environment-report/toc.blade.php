<div class="section-title">Table of Contents</div>
<table class="grid avoid">
    <tr><th style="width:15%">Section</th><th>Title</th></tr>
    @foreach(\App\Models\EnvironmentReport::SECTION_KEYS as $key)
        <tr>
            <td>{{ $section_numbers[$key] }}</td>
            <td>{{ $section_titles[$key] }}</td>
        </tr>
        @foreach(($section_subitems[$key] ?? []) as $item)
            <tr>
                <td>{{ $item['num'] }}</td>
                <td style="padding-left:14px;">{{ $item['title'] }}</td>
            </tr>
        @endforeach
    @endforeach
</table>
