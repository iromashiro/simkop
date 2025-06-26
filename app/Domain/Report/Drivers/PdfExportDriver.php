<?php
// app/Domain/Report/Drivers/PdfExportDriver.php
namespace App\Domain\Report\Drivers;

use App\Domain\Report\Contracts\ExportDriverInterface;
use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\DTOs\ExportResultDTO;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * PDF Export Driver using DomPDF
 */
class PdfExportDriver implements ExportDriverInterface
{
    public function export(ReportResultDTO $report, ExportRequestDTO $request): ExportResultDTO
    {
        $startTime = microtime(true);

        // Prepare data for PDF template
        $data = [
            'report' => $report,
            'options' => $request->options,
            'generated_at' => now()->format('d F Y H:i:s'),
            'cooperative_name' => $this->getCooperativeName($report->cooperativeId),
        ];

        // Generate PDF
        $pdf = Pdf::loadView('reports.pdf.template', $data)
            ->setPaper($request->options['paper_size'] ?? 'a4', $request->options['orientation'] ?? 'portrait')
            ->setOptions([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isPhpEnabled' => false, // Security: Disable PHP in templates
                'isRemoteEnabled' => false, // Security: Disable remote content
            ]);

        $content = $pdf->output();
        $generationTime = microtime(true) - $startTime;

        $filename = $request->filename ?? $this->generateFilename($report);

        return new ExportResultDTO(
            content: $content,
            mimeType: $this->getMimeType(),
            filename: $filename . '.' . $this->getFileExtension(),
            size: strlen($content),
            generationTime: $generationTime
        );
    }

    public function getSupportedOptions(): array
    {
        return [
            'paper_size' => ['a4', 'a3', 'letter', 'legal'],
            'orientation' => ['portrait', 'landscape'],
            'include_summary' => 'boolean',
            'include_details' => 'boolean',
            'font_size' => ['small', 'medium', 'large'],
        ];
    }

    public function getMimeType(): string
    {
        return 'application/pdf';
    }

    public function getFileExtension(): string
    {
        return 'pdf';
    }

    private function generateFilename(ReportResultDTO $report): string
    {
        $title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $report->title);
        return $title . '_' . now()->format('Y_m_d_H_i_s');
    }

    private function getCooperativeName(int $cooperativeId): string
    {
        return \DB::table('cooperatives')
            ->where('id', $cooperativeId)
            ->value('name') ?? 'Unknown Cooperative';
    }
}
