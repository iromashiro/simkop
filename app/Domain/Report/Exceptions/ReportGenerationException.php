<?php
// app/Domain/Report/Exceptions/ReportGenerationException.php
namespace App\Domain\Report\Exceptions;

/**
 * Custom exception for report generation errors
 */
class ReportGenerationException extends \Exception
{
    public function __construct(
        string $message = 'Report generation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get user-friendly error message
     */
    public function getUserMessage(): string
    {
        return 'Unable to generate the requested report. Please try again or contact support if the problem persists.';
    }

    /**
     * Check if error should be reported to monitoring
     */
    public function shouldReport(): bool
    {
        return true;
    }
}
