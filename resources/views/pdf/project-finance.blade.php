<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans; font-size: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px; text-align: left; }
        th { background: #0f766e; color: white; }
    </style>
</head>
<body>
<h2>Project Finance — {{ ucfirst(str_replace('-', ' ', $resource)) }}</h2>
@if ($resource === 'expenses')
    @php($columns = ['expense_date' => 'Date', 'category' => 'Category', 'description' => 'Description', 'quantity' => 'Qty', 'unit' => 'Unit', 'unit_price' => 'Price per Unit', 'amount' => 'Amount', 'payment_method' => 'Payment Method', 'invoice_no' => 'Invoice No.', 'do_no' => 'DO No.', 'vendor' => 'Vendor', 'amount_source_mismatch' => 'Amount Source Mismatch', 'amount_calculated' => 'Amount Calculated'])
    <table>
        <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            <tr>
                @foreach ($columns as $key => $label)
                    <td>{{ $key === 'expense_date' ? $row->expense_date?->format('Y-m-d') : ($key === 'amount_source_mismatch' ? ($row->amount_source_mismatch ? 'Yes' : 'No') : $row->$key) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
@else
    @php($first = $rows->first())
    @php($attributes = is_array($first) ? $first : ($first?->getAttributes() ?? []))
    <table>
        <thead><tr>@foreach ($attributes as $key => $value)<th>{{ $key }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($rows as $row)
            @php($values = is_array($row) ? $row : $row->getAttributes())
            <tr>@foreach ($values as $value)<td>{{ is_scalar($value) || $value === null ? $value : (string) $value }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
