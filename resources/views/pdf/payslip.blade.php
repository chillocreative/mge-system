<?php
    $name = $record->employee?->full_name
        ?: trim(($record->user->first_name ?? '') . ' ' . ($record->user->last_name ?? ''));
    $empNo = $record->employee?->employee_no ?? '—';
    $dept = $record->employee?->department?->name ?? $record->user?->department?->name ?? '—';
    $desig = $record->employee?->designation?->name ?? $record->user?->designation?->name ?? '—';
    $fmt = fn ($v) => $currency . ' ' . number_format((float) $v, 2);
    $period = $record->period_start?->format('d M Y') . ' – ' . $record->period_end?->format('d M Y');

    $earnings = [
        ['Basic Salary', $record->base_salary],
        ['Overtime', $record->overtime_pay],
        ['Allowances', $record->allowances],
        ['Bonus', $record->bonus],
    ];
    $statutory = [
        ['EPF (Employee)', $record->epf_employee],
        ['SOCSO (Employee)', $record->socso_employee],
        ['EIS (Employee)', $record->eis_employee],
        ['PCB (Tax)', $record->pcb],
        ['Zakat', $record->zakat],
    ];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    * { font-family: DejaVu Sans, sans-serif; }
    body { font-size: 12px; color: #1f2937; margin: 0; }
    .header { border-bottom: 3px solid #15803d; padding-bottom: 12px; margin-bottom: 18px; }
    .header td { vertical-align: top; }
    .logo { width: 70px; }
    .company { font-size: 16px; font-weight: bold; color: #15803d; }
    .muted { color: #6b7280; font-size: 11px; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .meta-table { width: 100%; margin-bottom: 16px; }
    .meta-table td { padding: 2px 0; font-size: 11px; }
    .label { color: #6b7280; width: 110px; }
    table.lines { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    table.lines th { background: #f0fdf4; color: #166534; text-align: left; padding: 6px 8px; font-size: 11px; border-bottom: 1px solid #bbf7d0; }
    table.lines td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; }
    .right { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #15803d; }
    .net { background: #15803d; color: #fff; padding: 10px 12px; font-size: 15px; font-weight: bold; }
    .two-col { width: 100%; }
    .two-col td { width: 50%; vertical-align: top; padding-right: 10px; }
    .footnote { margin-top: 24px; font-size: 10px; color: #9ca3af; text-align: center; }
</style>
</head>
<body>
    <table class="header"><tr>
        <td>
            @if($logo)<img src="{{ $logo }}" class="logo">@endif
        </td>
        <td>
            <div class="company">{{ $company['name'] ?? 'Multi Green Engineering Sdn. Bhd.' }}</div>
            @if(!empty($company['reg_no']))<div class="muted">Reg. No: {{ $company['reg_no'] }}</div>@endif
            @if(!empty($company['address']))<div class="muted">{{ $company['address'] }}</div>@endif
        </td>
        <td class="right">
            <h1>PAYSLIP</h1>
            <div class="muted">{{ $period }}</div>
        </td>
    </tr></table>

    <table class="meta-table"><tr>
        <td>
            <table><tr><td class="label">Employee</td><td><strong>{{ $name ?: '—' }}</strong></td></tr>
            <tr><td class="label">Employee No</td><td>{{ $empNo }}</td></tr></table>
        </td>
        <td>
            <table><tr><td class="label">Department</td><td>{{ $dept }}</td></tr>
            <tr><td class="label">Designation</td><td>{{ $desig }}</td></tr></table>
        </td>
    </tr></table>

    <table class="two-col"><tr>
        <td>
            <table class="lines">
                <tr><th>Earnings</th><th class="right">Amount</th></tr>
                @foreach($earnings as $row)
                    <tr><td>{{ $row[0] }}</td><td class="right">{{ $fmt($row[1]) }}</td></tr>
                @endforeach
                <tr class="total-row"><td>Gross Pay</td><td class="right">{{ $fmt($record->gross_salary) }}</td></tr>
            </table>
        </td>
        <td>
            <table class="lines">
                <tr><th>Deductions</th><th class="right">Amount</th></tr>
                @foreach($statutory as $row)
                    <tr><td>{{ $row[0] }}</td><td class="right">{{ $fmt($row[1]) }}</td></tr>
                @endforeach
                @foreach($record->deductionItems as $d)
                    <tr><td>{{ ucwords(str_replace('_',' ',$d->type)) }}{{ $d->description ? ' — '.$d->description : '' }}</td><td class="right">{{ $fmt($d->amount) }}</td></tr>
                @endforeach
                <tr class="total-row"><td>Total Deductions</td><td class="right">{{ $fmt($record->deductions) }}</td></tr>
            </table>
        </td>
    </tr></table>

    <div class="net">
        <table style="width:100%"><tr>
            <td style="color:#fff">NET PAY</td>
            <td class="right" style="color:#fff">{{ $fmt($record->net_salary) }}</td>
        </tr></table>
    </div>

    <table class="lines" style="margin-top:16px">
        <tr><th>Employer Contributions (not deducted from pay)</th><th class="right">Amount</th></tr>
        <tr><td>EPF (Employer)</td><td class="right">{{ $fmt($record->epf_employer) }}</td></tr>
        <tr><td>SOCSO (Employer)</td><td class="right">{{ $fmt($record->socso_employer) }}</td></tr>
        <tr><td>EIS (Employer)</td><td class="right">{{ $fmt($record->eis_employer) }}</td></tr>
    </table>

    <div class="footnote">
        This is a computer-generated payslip and does not require a signature.
        Generated on {{ now()->format('d M Y, H:i') }}.
    </div>
</body>
</html>
