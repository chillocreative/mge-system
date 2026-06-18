<?php
    $name = $record->employee?->full_name
        ?: trim(($record->user->first_name ?? '') . ' ' . ($record->user->last_name ?? ''));
    $period = $record->period_start?->format('F Y');
?>
<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color:#1f2937; line-height:1.6;">
    <p>Dear {{ $name ?: 'Employee' }},</p>

    <p>Please find attached your payslip for <strong>{{ $period }}</strong>.</p>

    <p>
        Net Pay: <strong>{{ config('payroll.currency') }} {{ number_format((float) $record->net_salary, 2) }}</strong>
    </p>

    <p>This is a confidential document. If you received it in error, please delete it and notify HR.</p>

    <p>Regards,<br>
    {{ $company['name'] ?? 'Multi Green Engineering Sdn. Bhd.' }} — HR Department</p>
</body>
</html>
