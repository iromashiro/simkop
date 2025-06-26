<?php
// app/Domain/Report/DTOs/ExportResultDTO.php
namespace App\Domain\Report\DTOs;

use Illuminate\Http\Response;

/**
 * DTO for export results
 */
class ExportResultDTO
{
    public function __construct(
        public readonly string $content,
        public readonly string $mimeType,
        public readonly string $filename,
        public readonly int $size,
        public readonly float $generationTime,
        public readonly array $headers = []
    ) {}

    public function toResponse(): Response
    {
        $headers = array_merge([
            'Content-Type' => $this->mimeType,
            'Content-Disposition' => "attachment; filename=\"{$this->filename}\"",
            'Content-Length' => $this->size,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ], $this->headers);

        return response($this->content, 200, $headers);
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getGenerationTime(): float
    {
        return $this->generationTime;
    }
}
