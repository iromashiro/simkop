<?php
// app/Domain/Report/Drivers/ExcelExportDriver.php
namespace App\Domain\Report\Drivers;

use App\Domain\Report\Contracts\ExportDriverInterface;
use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\DTOs\ExportResultDTO;
use Maatwebsite\Excel\Facades\Excel;
use App\Domain\Report\Exports\ReportExport;

/**
 * Excel Export Driver using Laravel Excel
 */
class ExcelExportDriver implements ExportDriverInterface
{
    public function export(ReportResultDTO $report, ExportRequestDTO $request): ExportResultDTO
    {
        $startTime = microtime(true);

        // Create Excel export instance
        $export = new ReportExport($report, $request->options);

        // Generate Excel file in memory
        $content = Excel::raw($export, \Maatwebsite\Excel\Excel::XLSX);
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
            'include_summary' => 'boolean',
            'include_charts' => 'boolean',
            'auto_filter' => 'boolean',
            'freeze_header' => 'boolean',
            'format_numbers' => 'boolean',
        ];
    }

    public function getMimeType(): string
    {
        return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    public function getFileExtension(): string
    {
        return 'xlsx';
    }

    private function generateFilename(ReportResultDTO $report): string
    {
        $title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $report->title);
        return $title . '_' . now()->format('Y_m_d_H_i_s');
    }
}
