<?php
// app/Domain/Report/DTOs/ExportRequestDTO.php
namespace App\Domain\Report\DTOs;

/**
 * DTO for export requests
 */
class ExportRequestDTO
{
    public function __construct(
        public readonly string $format,
        public readonly array $options = [],
        public readonly ?string $filename = null,
        public readonly int $estimatedSize = 0
    ) {
        $this->validate();
    }

    private function validate(): void
    {
        $allowedFormats = ['pdf', 'excel', 'csv', 'html'];
        if (!in_array($this->format, $allowedFormats)) {
            throw new \InvalidArgumentException("Invalid format: {$this->format}");
        }

        if ($this->filename && !preg_match('/^[a-zA-Z0-9_\-\s\.]+$/', $this->filename)) {
            throw new \InvalidArgumentException('Invalid filename format');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            format: $data['format'],
            options: $data['options'] ?? [],
            filename: $data['filename'] ?? null,
            estimatedSize: $data['estimated_size'] ?? 0
        );
    }
}
