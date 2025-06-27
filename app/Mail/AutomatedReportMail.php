<?php

namespace App\Mail;

use App\Domain\Reporting\DTOs\ReportParametersDTO;
use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class AutomatedReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private ReportParametersDTO $parameters,
        private string $filePath,
        private Cooperative $cooperative
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Laporan Otomatis - {$this->cooperative->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.automated-report',
            with: [
                'cooperative' => $this->cooperative,
                'parameters' => $this->parameters,
                'reportType' => $this->getReportTypeName(),
                'period' => $this->parameters->startDate . ' - ' . $this->parameters->endDate,
            ],
        );
    }

    public function attachments(): array
    {
        if (!Storage::disk('local')->exists($this->filePath)) {
            return [];
        }

        return [
            Attachment::fromStorage($this->filePath)
                ->as($this->getAttachmentName())
                ->withMime($this->getMimeType()),
        ];
    }

    private function getReportTypeName(): string
    {
        return match ($this->parameters->reportType) {
            'financial_statement' => 'Laporan Keuangan',
            'member_report' => 'Laporan Anggota',
            'loan_report' => 'Laporan Pinjaman',
            'savings_report' => 'Laporan Simpanan',
            'comprehensive_report' => 'Laporan Komprehensif',
            default => 'Laporan',
        };
    }

    private function getAttachmentName(): string
    {
        $reportName = $this->getReportTypeName();
        $date = now()->format('Y-m-d');
        $extension = $this->parameters->isPdfFormat() ? 'pdf' : 'xlsx';

        return "{$reportName}_{$this->cooperative->name}_{$date}.{$extension}";
    }

    private function getMimeType(): string
    {
        return $this->parameters->isPdfFormat()
            ? 'application/pdf'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }
}
