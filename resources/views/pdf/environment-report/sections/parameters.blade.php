@php
    $data = $sections['parameters'];
    $groups = [
        'Water Quality' => $data['water'] ?? [],
        'Air Quality' => $data['air'] ?? [],
        'Noise Levels' => $data['noise'] ?? [],
        'Vibration Levels' => $data['vibration'] ?? [],
    ];
    $max = max(array_map('count', $groups) ?: [0]);
@endphp
<div class="section-title">{{ $section_numbers['parameters'] }} {{ $section_titles['parameters'] }}</div>

<div class="sub-title">{{ $section_subitems['parameters'][0]['num'] }} {{ $section_subitems['parameters'][0]['title'] }}</div>
<table class="grid">
    <tr>
        @foreach(array_keys($groups) as $label)
            <th style="width:25%">{{ $label }}</th>
        @endforeach
    </tr>
    @for($i = 0; $i < $max; $i++)
        <tr>
            @foreach($groups as $items)
                <td>{{ $items[$i] ?? '' }}</td>
            @endforeach
        </tr>
    @endfor
</table>

<div class="sub-title">{{ $section_subitems['parameters'][1]['num'] }} {{ $section_subitems['parameters'][1]['title'] }}</div>
<table class="grid">
    <tr>
        <th style="width:6%">No.</th>
        <th>Sampling Session</th>
        <th>Water Quality</th>
        <th>Air Quality</th>
        <th>Noise Levels</th>
        <th>Vibration Levels</th>
    </tr>
    @foreach(($data['periods'] ?? []) as $period)
        <tr>
            <td class="center">{{ $period['no'] }}</td>
            <td>{{ $period['session'] }}</td>
            <td class="center">{{ $period['water'] }}</td>
            <td class="center">{{ $period['air'] }}</td>
            <td class="center">{{ $period['noise'] }}</td>
            <td class="center">{{ $period['vibration'] }}</td>
        </tr>
    @endforeach
</table>
