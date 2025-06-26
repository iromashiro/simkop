<?php
// app/Domain/Analytics/DTOs/ValidationResultDTO.php
namespace App\Domain\Analytics\DTOs;

use Carbon\Carbon;

/**
 * DTO for analytics validation results
 */
class ValidationResultDTO
{
    public function __construct(
        public readonly bool $isValid,
        public readonly array $warnings,
        public readonly array $errors,
        public readonly array $suggestions,
        public readonly Carbon $validatedAt
    ) {}

    public function hasWarnings(): bool
    {
        return !empty($this->warnings);
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function hasSuggestions(): bool
    {
        return !empty($this->suggestions);
    }

    public function getWarningCount(): int
    {
        return count($this->warnings);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function getSuggestionCount(): int
    {
        return count($this->suggestions);
    }

    public function getHighSeverityWarnings(): array
    {
        return array_filter($this->warnings, function ($warning) {
            return ($warning['severity'] ?? 'low') === 'high';
        });
    }

    public function toArray(): array
    {
        return [
            'is_valid' => $this->isValid,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'suggestions' => $this->suggestions,
            'validated_at' => $this->validatedAt->toISOString(),
            'summary' => [
                'warning_count' => $this->getWarningCount(),
                'error_count' => $this->getErrorCount(),
                'suggestion_count' => $this->getSuggestionCount(),
                'high_severity_warnings' => count($this->getHighSeverityWarnings()),
            ],
        ];
    }
}
