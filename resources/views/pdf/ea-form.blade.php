<?php
    $fmt = fn ($v) => $currency . ' ' . number_format((float) $v, 2);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #1f2937; margin: 0; }
    .header { border-bottom: 3px solid #15803d; padding-bottom: 10px; margin-bottom: 6px; }
    .header td { vertical-align: top; }
    .logo { width: 64px; }
    .company { font-size: 15px; font-weight: bold; color: #15803d; }
    .muted { color: #6b7280; font-size: 11px; }
    .title { text-align: center; margin: 10px 0 4px; }
    .title h1 { font-size: 16px; margin: 0; }
    .title .sub { font-size: 11px; color: #6b7280; }
    table.box { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.box td, table.box th { border: 1px solid #d1d5db; padding: 6px 8px; font-size: 11px; }
    table.box th { background: #f0fdf4; text-align: left; color: #166534; }
    .section { font-weight: bold; background: #f9fafb; }
    .right { text-align: right; }
    .label { color: #6b7280; width: 45%; }
    .note { font-size: 10px; color: #9ca3af; margin-top: 18px; }
</style>
</head>
<body>
    <table class="header"><tr>
        <td style="width:70px">@if($logo)<img src="{{ $logo }}" class="logo">@endif</td>
        <td>
            <div class="company">{{ $company['name'] ?? 'Multi Green Engineering Sdn. Bhd.' }}</div>
            @if(!empty($company['employer_no']))<div class="muted">Employer No (E): {{ $company['employer_no'] }}</div>@endif
            @if(!empty($company['address']))<div class="muted">{{ $company['address'] }}</div>@endif
        </td>
    </tr></table>

    <div class="title">
        <h1>BORANG / FORM EA (C.P.8A) — {{ $year }}</h1>
        <div class="sub">Statement of Remuneration from Employment for the Year Ended 31 December {{ $year }}</div>
        <div class="sub">(Income Tax Act 1967 — Section 83(1A))</div>
    </div>

    <table class="box">
        <tr><th colspan="2">A. Particulars of Employee</th></tr>
        <tr><td class="label">Name</td><td>{{ $employee->full_name }}</td></tr>
        <tr><td class="label">Employee No</td><td>{{ $employee->employee_no }}</td></tr>
        <tr><td class="label">IC / Passport No</td><td>{{ $employee->ic_passport_no ?? '—' }}</td></tr>
        <tr><td class="label">Income Tax No</td><td>{{ $employee->tax_no ?? '—' }}</td></tr>
        <tr><td class="label">EPF No</td><td>{{ $employee->epf_no ?? '—' }}</td></tr>
        <tr><td class="label">SOCSO No</td><td>{{ $employee->socso_no ?? '—' }}</td></tr>
        <tr><td class="label">Designation</td><td>{{ $employee->designation?->name ?? '—' }}</td></tr>
        <tr><td class="label">Department</td><td>{{ $employee->department?->name ?? '—' }}</td></tr>
    </table>

    <table class="box">
        <tr><th>B. Employment Income</th><th class="right">Amount ({{ $year }})</th></tr>
        <tr><td>1. Gross salary, wages, overtime &amp; allowances</td><td class="right">{{ $fmt($totals['gross_remuneration']) }}</td></tr>
        <tr><td>2. Bonus / incentive</td><td class="right">{{ $fmt($totals['bonus']) }}</td></tr>
        <tr><td>3. Benefits-in-kind (BIK)</td><td class="right">{{ $fmt(0) }}</td></tr>
        <tr><td>4. Value of living accommodation (VOLA)</td><td class="right">{{ $fmt(0) }}</td></tr>
        <tr class="section"><td>Total Gross Remuneration</td><td class="right">{{ $fmt($totals['gross_remuneration'] + $totals['bonus']) }}</td></tr>
    </table>

    <table class="box">
        <tr><th>C. Deductions / Contributions</th><th class="right">Amount ({{ $year }})</th></tr>
        <tr><td>Monthly Tax Deduction (PCB / MTD)</td><td class="right">{{ $fmt($totals['pcb']) }}</td></tr>
        <tr><td>Zakat paid via salary deduction</td><td class="right">{{ $fmt($totals['zakat']) }}</td></tr>
        <tr><td>Employee EPF contribution</td><td class="right">{{ $fmt($totals['epf_employee']) }}</td></tr>
        <tr><td>Employee SOCSO contribution</td><td class="right">{{ $fmt($totals['socso_employee']) }}</td></tr>
        <tr><td>Employee EIS contribution</td><td class="right">{{ $fmt($totals['eis_employee']) }}</td></tr>
    </table>

    <div class="note">
        Aggregated from {{ $totals['months'] }} payroll record(s) for {{ $year }}. BIK and VOLA must be
        completed manually where applicable. This is a system-generated draft of the EA Form for
        book-keeping; verify all figures against the official LHDN C.P.8A template before issuing.
        Generated on {{ now()->format('d M Y, H:i') }}.
    </div>
</body>
</html>
