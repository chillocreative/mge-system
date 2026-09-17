@php
    $partyLogo = fn (string $role) => $logos[$role] ?? null;
@endphp
<div class="section">
    @if(!empty($data['placeholder']))
        <p class="placeholder">No data.</p>
    @else
        <div class="cover-title">Monthly Progress Report No.{{ $data['report_no'] ?? $report->report_no }} ({{ $data['report_no_words'] ?? '' }})</div>
        <table class="grid">
            <tr><th style="width:30%">Project Title</th><td>{{ $data['project_title'] ?? '' }}</td></tr>
            <tr><th>Contract No.</th><td>{{ $data['contract_no'] ?? '' }}</td></tr>
            <tr><th>Reporting Period</th><td>{{ $data['period_label'] ?? '' }}</td></tr>
        </table>

        <table class="grid">
            <tr>
                <th style="width:25%">Role</th>
                <th style="width:15%">Logo</th>
                <th>Company</th>
                <th>Address</th>
            </tr>
            @foreach(['client' => 'Client', 'so' => 'Superintending Officer', 'consultant' => 'Consultant', 'contractor' => 'Contractor'] as $slot => $label)
                @php $party = $data[$slot] ?? null; @endphp
                <tr>
                    <td>{{ $label }}</td>
                    <td>
                        @php
                            $logoRole = match ($slot) { 'client' => 'owner', 'so' => 'superintending_officer', default => $slot };
                            $logoSrc = $partyLogo($logoRole);
                        @endphp
                        @if($logoSrc)
                            <img src="{{ $logoSrc }}" style="max-height:12mm;max-width:100%;">
                        @endif
                    </td>
                    <td>{{ $party['name'] ?? '-' }}</td>
                    <td>{{ $party['address'] ?? '-' }}</td>
                </tr>
            @endforeach
        </table>

        <table class="grid avoid">
            <tr><th>Prepared By</th><th>Verified By</th><th>Accepted By</th></tr>
            <tr>
                @foreach(['prepared', 'verified', 'accepted'] as $slot)
                    @php $sig = collect($data['signatories'] ?? [])->firstWhere('slot', $slot); @endphp
                    <td style="width:33.33%">
                        <div class="signature-box"></div>
                        <div>Name: {{ $sig['name'] ?? '' }}</div>
                        <div>Designation: {{ $sig['designation'] ?? '' }}</div>
                        <div>Company: {{ $sig['company'] ?? '' }}</div>
                    </td>
                @endforeach
            </tr>
        </table>
    @endif
</div>
