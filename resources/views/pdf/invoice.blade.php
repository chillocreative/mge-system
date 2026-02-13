<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1f2937; line-height: 1.5; }
        .container { padding: 40px; }
        .header { display: table; width: 100%; margin-bottom: 30px; }
        .header-left { display: table-cell; width: 60%; vertical-align: top; }
        .header-right { display: table-cell; width: 40%; vertical-align: top; text-align: right; }
        .company-name { font-size: 22px; font-weight: bold; color: #0f172a; margin-bottom: 4px; }
        .company-subtitle { font-size: 10px; color: #334155; }
        .invoice-title { font-size: 28px; font-weight: bold; color: #0f172a; margin-bottom: 6px; }
        .invoice-number { font-size: 13px; color: #6b7280; }
        .invoice-status { display: inline-block; padding: 3px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-draft { background: #f3f4f6; color: #374151; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-partially_paid { background: #fef3c7; color: #92400e; }
        .status-overdue { background: #fee2e2; color: #991b1b; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        .details-row { display: table; width: 100%; margin-bottom: 30px; }
        .details-col { display: table-cell; width: 50%; vertical-align: top; }
        .details-label { font-size: 10px; font-weight: bold; text-transform: uppercase; color: #9ca3af; margin-bottom: 4px; letter-spacing: 0.5px; }
        .details-value { font-size: 12px; color: #1f2937; margin-bottom: 2px; }
        .details-value strong { color: #111827; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        table.items thead th { background: #0f172a; color: #fff; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; text-align: left; }
        table.items thead th:last-child, table.items thead th:nth-child(3), table.items thead th:nth-child(4) { text-align: right; }
        table.items tbody td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        table.items tbody td:last-child, table.items tbody td:nth-child(3), table.items tbody td:nth-child(4) { text-align: right; }
        table.items tbody tr:nth-child(even) { background: #f9fafb; }
        .totals { width: 300px; margin-left: auto; margin-bottom: 30px; }
        .totals-row { display: table; width: 100%; padding: 6px 0; }
        .totals-label { display: table-cell; text-align: left; color: #6b7280; font-size: 12px; }
        .totals-value { display: table-cell; text-align: right; font-size: 12px; }
        .totals-row.grand { border-top: 2px solid #0f172a; padding-top: 10px; margin-top: 4px; }
        .totals-row.grand .totals-label, .totals-row.grand .totals-value { font-size: 16px; font-weight: bold; color: #0f172a; }
        .totals-row.paid .totals-value { color: #059669; }
        .totals-row.due .totals-value { color: #dc2626; font-weight: bold; }
        .notes { margin-top: 20px; padding: 16px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb; }
        .notes-title { font-size: 11px; font-weight: bold; text-transform: uppercase; color: #9ca3af; margin-bottom: 6px; }
        .notes-text { font-size: 11px; color: #4b5563; }
        .footer { margin-top: 40px; text-align: center; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .footer p { font-size: 10px; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-left">
                <div class="company-name">MGE-PMS</div>
                <div class="company-subtitle">Project Management System</div>
            </div>
            <div class="header-right">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
                <div style="margin-top: 6px;">
                    <span class="invoice-status status-{{ $invoice->status }}">{{ str_replace('_', ' ', $invoice->status) }}</span>
                </div>
            </div>
        </div>

        <!-- Bill To / Invoice Details -->
        <div class="details-row">
            <div class="details-col">
                <div class="details-label">Bill To</div>
                <div class="details-value"><strong>{{ $invoice->client->company_name }}</strong></div>
                @if($invoice->client->contact_person)
                    <div class="details-value">{{ $invoice->client->contact_person }}</div>
                @endif
                @if($invoice->client->email)
                    <div class="details-value">{{ $invoice->client->email }}</div>
                @endif
                @if($invoice->client->phone)
                    <div class="details-value">{{ $invoice->client->phone }}</div>
                @endif
                @if($invoice->client->address)
                    <div class="details-value">{{ $invoice->client->address }}</div>
                    @if($invoice->client->city)
                        <div class="details-value">{{ $invoice->client->city }}{{ $invoice->client->state ? ', ' . $invoice->client->state : '' }} {{ $invoice->client->zip_code }}</div>
                    @endif
                @endif
            </div>
            <div class="details-col" style="text-align: right;">
                <div class="details-label">Invoice Details</div>
                <div class="details-value"><strong>Issue Date:</strong> {{ $invoice->issue_date->format('M d, Y') }}</div>
                <div class="details-value"><strong>Due Date:</strong> {{ $invoice->due_date->format('M d, Y') }}</div>
                @if($invoice->project)
                    <div class="details-value"><strong>Project:</strong> {{ $invoice->project->name }}</div>
                    <div class="details-value" style="color: #6b7280;">({{ $invoice->project->code }})</div>
                @endif
            </div>
        </div>

        <!-- Items Table -->
        <table class="items">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Description</th>
                    <th style="width: 10%;">Qty</th>
                    <th style="width: 20%;">Unit Price</th>
                    <th style="width: 20%;">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->items as $index => $item)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $item->description }}</td>
                    <td style="text-align: right;">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }} {{ $item->unit }}</td>
                    <td style="text-align: right;">{{ $invoice->currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">{{ $invoice->currency }} {{ number_format($item->amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals">
            <div class="totals-row">
                <div class="totals-label">Subtotal</div>
                <div class="totals-value">{{ $invoice->currency }} {{ number_format($invoice->subtotal, 2) }}</div>
            </div>
            @if($invoice->tax_rate > 0)
            <div class="totals-row">
                <div class="totals-label">Tax ({{ $invoice->tax_rate }}%)</div>
                <div class="totals-value">{{ $invoice->currency }} {{ number_format($invoice->tax_amount, 2) }}</div>
            </div>
            @endif
            @if($invoice->discount > 0)
            <div class="totals-row">
                <div class="totals-label">Discount</div>
                <div class="totals-value">- {{ $invoice->currency }} {{ number_format($invoice->discount, 2) }}</div>
            </div>
            @endif
            <div class="totals-row grand">
                <div class="totals-label">Total</div>
                <div class="totals-value">{{ $invoice->currency }} {{ number_format($invoice->total, 2) }}</div>
            </div>
            @if($invoice->amount_paid > 0)
            <div class="totals-row paid">
                <div class="totals-label">Amount Paid</div>
                <div class="totals-value">{{ $invoice->currency }} {{ number_format($invoice->amount_paid, 2) }}</div>
            </div>
            @endif
            @if($invoice->balance_due > 0)
            <div class="totals-row due">
                <div class="totals-label">Balance Due</div>
                <div class="totals-value">{{ $invoice->currency }} {{ number_format($invoice->balance_due, 2) }}</div>
            </div>
            @endif
        </div>

        <!-- Notes & Terms -->
        @if($invoice->notes || $invoice->terms)
        <div class="notes">
            @if($invoice->notes)
                <div class="notes-title">Notes</div>
                <div class="notes-text">{{ $invoice->notes }}</div>
            @endif
            @if($invoice->terms)
                <div class="notes-title" style="margin-top: 10px;">Terms & Conditions</div>
                <div class="notes-text">{{ $invoice->terms }}</div>
            @endif
        </div>
        @endif

        <!-- Payments History -->
        @if($invoice->payments->count() > 0)
        <div style="margin-top: 24px;">
            <div class="details-label" style="margin-bottom: 8px;">Payment History</div>
            <table class="items" style="font-size: 11px;">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($invoice->payments as $payment)
                    <tr>
                        <td>{{ $payment->payment_date->format('M d, Y') }}</td>
                        <td>{{ str_replace('_', ' ', $payment->method) }}</td>
                        <td>{{ $payment->reference ?: '-' }}</td>
                        <td style="text-align: right;">{{ $invoice->currency }} {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <!-- Footer -->
        <div class="footer">
            <p>Thank you for your business.</p>
            <p>MGE-PMS &mdash; Project Management System</p>
        </div>
    </div>
</body>
</html>
