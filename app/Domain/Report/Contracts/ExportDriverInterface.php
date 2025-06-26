<?php
// app/Domain/Report/Contracts/ExportDriverInterface.php
namespace App\Domain\Report\Contracts;

use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\DTOs\ExportResultDTO;

/**
 * Contract for export drivers
 */
interface ExportDriverInterface
{
    /**
     * Export report to specific format
     */
    public function export(ReportResultDTO $report, ExportRequestDTO $request): ExportResultDTO;

    /**
     * Get supported options for this driver
     */
    public function getSupportedOptions(): array;

    /**
     * Get MIME type for this format
     */
    public function getMimeType(): string;

    /**
     * Get file extension for this format
     */
    public function getFileExtension(): string;
}
