@php
    $p = $parties;
    $addr = fn (array $party) => trim(($party['name'] ?? '')."\n".implode("\n", $party['address_lines'] ?? []));
    $rows = [
        ['label' => 'PROJECT TITLE', 'value' => $project->name],
        ['label' => 'CONTRACT NO.', 'value' => $contract_no],
        ['label' => 'PROJECT OWNER', 'value' => $addr($p['owner'])],
        ['label' => 'SUPERINTENDING OFFICER (SO)', 'value' => $addr($p['so'])],
        ['label' => 'CONSULTANT', 'value' => $addr($p['consultant'])],
    ];
@endphp
<div class="cover-logo">
    @if($logo_so)
        <img src="{{ $logo_so }}" alt="Logo">
    @endif
</div>
<div class="cover-so-name">{{ $so_name ?: 'JABATAN PENGAIRAN DAN SALIRAN NEGERI JOHOR' }}</div>

<div class="cover-title">{{ $report_title ?: ('MONTHLY ENVIRONMENT REPORT NO.'.$report_no) }}</div>
<div class="cover-period">({{ $period_label }})</div>

<table class="kv-plain">
    @foreach($rows as $row)
        <tr>
            <td class="kv-label">{{ $row['label'] }}</td>
            <td class="kv-colon">:</td>
            <td class="kv-value">
                @foreach(explode("\n", $row['value']) as $line)
                    {{ $line }}@if(! $loop->last)<br>@endif
                @endforeach
            </td>
        </tr>
    @endforeach
    <tr>
        <td class="kv-label">CONTRACTOR</td>
        <td class="kv-colon">:</td>
        <td class="kv-value">
            <table style="width:100%"><tr>
                <td style="border:none;padding:0;vertical-align:top;">
                    {{ $p['contractor']['name'] }}
                    @foreach($p['contractor']['address_lines'] as $line)
                        <br>{{ $line }}
                    @endforeach
                </td>
                <td style="border:none;padding:0;width:26mm;text-align:right;vertical-align:top;">
                    @if($logo_mge)
                        <img src="{{ $logo_mge }}" style="max-height:14mm;max-width:100%;">
                    @endif
                </td>
            </tr></table>
        </td>
    </tr>
</table>

<table class="grid avoid" style="margin-top:18px;">
    <tr><th>Prepared By:</th><th>Verified By:</th><th>Accepted By:</th></tr>
    <tr>
        @foreach(['prepared', 'verified', 'accepted'] as $slot)
            @php $sig = $signatories[$slot]; @endphp
            <td style="width:33.33%">
                <div class="signature-box"></div>
                <div class="sig-name">{{ $sig['name'] }}</div>
                <div>{{ $sig['designation'] }}</div>
                <div>{{ $sig['company'] }}</div>
            </td>
        @endforeach
    </tr>
</table>
