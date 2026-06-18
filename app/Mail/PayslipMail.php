<?php

namespace App\Mail;

use App\Models\PayrollRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayslipMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PayrollRecord $record,
        public string $pdfData
    ) {}

    public function envelope(): Envelope
    {
        $period = $this->record->period_start?->format('M Y') ?? 'period';

        return new Envelope(
            subject: "Payslip for {$period}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.payslip',
            with: [
                'record' => $this->record,
                'company' => config('payroll.company'),
            ],
        );
    }

    public function attachments(): array
    {
        $period = $this->record->period_start?->format('Y-m') ?? 'payslip';

        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $this->pdfData, "payslip-{$period}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
