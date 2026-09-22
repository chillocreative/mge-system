@php
    $data = $sections['flow_chart'];
    $box = fn ($item) => '<div class="flow-box"><div class="t">'.e($item['title'] ?? '').'</div>'
        .implode('', array_map(fn ($l) => '<div>'.e($l).'</div>', $item['lines'] ?? [])).'</div>';
@endphp
<div class="section-title">{{ $section_numbers['flow_chart'] }} {{ $section_titles['flow_chart'] }}</div>

<table class="flow avoid">
    <tr><td>{!! $box($data['proponent'] ?? []) !!}</td></tr>
    <tr><td class="flow-arrow">&#8595;</td></tr>
    <tr><td>{!! $box($data['implementer'] ?? []) !!}</td></tr>
    <tr><td class="flow-arrow">&#8595;</td></tr>
    <tr><td>{!! $box($data['main_contractor'] ?? []) !!}</td></tr>
    <tr><td class="flow-arrow">&#8595;</td></tr>
    <tr>
        <td>
            <table class="flow">
                <tr>
                    <td style="width:50%">{!! $box($data['env_officer'] ?? []) !!}</td>
                    <td style="width:50%">{!! $box($data['env_consultant'] ?? []) !!}</td>
                </tr>
                <tr>
                    <td></td>
                    <td>
                        <table class="flow" style="margin-top:6px;">
                            <tr>
                                @foreach(($data['plans'] ?? []) as $plan)
                                    <td style="width:50%">
                                        <div class="flow-box" style="font-size:8.5px;">{{ $plan }}</div>
                                    </td>
                                @endforeach
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
