<?php
// app/Domain/Report/Contracts/ReportInterface.php
namespace App\Domain\Report\Contracts;

use App\Domain\Report\DTOs\ReportParameterDTO;
use App\Domain\Report\DTOs\ReportResultDTO;

/**
 * Contract for all financial reports in HERMES system
 * Based on SRS Section 3.4 - Financial Reporting Requirements
 */
interface ReportInterface
{
    /**
     * Generate the report with given parameters
     */
    public function generate(ReportParameterDTO $parameters): ReportResultDTO;

    /**
     * Get report metadata (title, description, required parameters)
     */
    public function getMetadata(): array;

    /**
     * Validate report parameters
     */
    public function validateParameters(ReportParameterDTO $parameters): bool;

    /**
     * Get supported export formats
     */
    public function getSupportedFormats(): array;
}
