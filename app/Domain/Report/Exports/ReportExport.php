<?php
// app/Domain/Report/Exports/ReportExport.php
namespace App\Domain\Report\Exports;

use App\Domain\Report\DTOs\ReportResultDTO;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\Log;

/**
 * MEMORY OPTIMIZED: Excel export implementation with chunk processing
 */
class ReportExport implements WithMultipleSheets
{
    public function __construct(
        private readonly ReportResultDTO $report,
        private readonly array $options = []
    ) {}

    public function sheets(): array
    {
        $sheets = [];

        // Main data sheet with memory optimization
        $sheets[] = new OptimizedReportDataSheet($this->report, $this->options);

        // Summary sheet if requested
        if ($this->options['include_summary'] ?? true) {
            $sheets[] = new ReportSummarySheet($this->report, $this->options);
        }

        // Charts sheet if requested and data is not too large
        if (($this->options['include_charts'] ?? false) && !$this->isLargeDataset()) {
            $sheets[] = new ReportChartsSheet($this->report, $this->options);
        }

        return $sheets;
    }

    private function isLargeDataset(): bool
    {
        return $this->estimateDataSize($this->report->data) > 5000;
    }

    private function estimateDataSize(array $data): int
    {
        if (isset($data['members'])) {
            return count($data['members']);
        } elseif (isset($data['assets'])) {
            return count($data['assets']) + count($data['liabilities']) + count($data['equity']);
        }

        return array_sum(array_map('count', array_filter($data, 'is_array')));
    }
}

/**
 * MEMORY OPTIMIZED: Main data sheet with chunked processing
 */
class OptimizedReportDataSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    private const CHUNK_SIZE = 1000;
    private const MEMORY_LIMIT = 200 * 1024 * 1024; // 200MB

    public function __construct(
        private readonly ReportResultDTO $report,
        private readonly array $options = []
    ) {}

    public function array(): array
    {
        $startMemory = memory_get_usage(true);

        Log::info('Starting Excel data transformation', [
            'memory_start' => $startMemory,
            'estimated_rows' => $this->estimateRowCount(),
        ]);

        try {
            // For large datasets, use chunked processing
            if ($this->isLargeDataset()) {
                return $this->getChunkedData();
            }

            // For smaller datasets, use direct transformation
            return $this->transformDataForExcel($this->report->data);
        } catch (\Exception $e) {
            Log::error('Excel data transformation failed', [
                'error' => $e->getMessage(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
            ]);
            throw $e;
        }
    }

    public function headings(): array
    {
        return $this->generateHeadings($this->report->data);
    }

    public function title(): string
    {
        return 'Data';
    }

    public function styles(Worksheet $sheet)
    {
        $lastColumn = $this->getLastColumn();

        return [
            // Header row styling
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '366092']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            // Data rows styling
            "A2:{$lastColumn}1000" => [
                'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_TOP],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
            // Number columns formatting
            $this->getNumberColumns() => [
                'numberFormat' => ['formatCode' => '#,##0.00'],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
            ],
        ];
    }

    /**
     * MEMORY FIX: Check if dataset is large
     */
    private function isLargeDataset(): bool
    {
        $estimatedRows = $this->estimateRowCount();
        $estimatedMemory = $estimatedRows * 1024; // Rough estimate: 1KB per row

        return $estimatedRows > 5000 || $estimatedMemory > 50 * 1024 * 1024; // 50MB
    }

    /**
     * MEMORY FIX: Process data in chunks to prevent memory exhaustion
     */
    private function getChunkedData(): array
    {
        $rows = [];
        $processedRows = 0;

        Log::info('Processing large dataset in chunks', [
            'chunk_size' => self::CHUNK_SIZE,
            'estimated_rows' => $this->estimateRowCount(),
        ]);

        try {
            if (isset($this->report->data['members'])) {
                $rows = $this->processChunkedMembers();
            } elseif (isset($this->report->data['assets'])) {
                $rows = $this->processChunkedAccounts();
            } else {
                $rows = $this->processChunkedGeneric();
            }

            Log::info('Chunked processing completed', [
                'total_rows' => count($rows),
                'memory_peak' => memory_get_peak_usage(true),
            ]);

            return $rows;
        } catch (\Exception $e) {
            Log::error('Chunked processing failed', [
                'error' => $e->getMessage(),
                'processed_rows' => $processedRows,
                'memory_usage' => memory_get_usage(true),
            ]);
            throw $e;
        }
    }

    /**
     * Process member data in chunks
     */
    private function processChunkedMembers(): array
    {
        $rows = [];
        $members = $this->report->data['members'];
        $chunks = array_chunk($members, self::CHUNK_SIZE);

        foreach ($chunks as $chunkIndex => $chunk) {
            Log::debug("Processing member chunk {$chunkIndex}", [
                'chunk_size' => count($chunk),
                'memory_usage' => memory_get_usage(true),
            ]);

            foreach ($chunk as $member) {
                $rows[] = $this->transformMemberData($member);
            }

            // Memory management
            $this->manageMemory($chunkIndex);
        }

        return $rows;
    }

    /**
     * Process account data in chunks
     */
    private function processChunkedAccounts(): array
    {
        $rows = [];

        // Process each account category
        $categories = [
            ['data' => $this->report->data['assets'] ?? [], 'label' => 'ASSETS'],
            ['data' => $this->report->data['liabilities'] ?? [], 'label' => 'LIABILITIES'],
            ['data' => $this->report->data['equity'] ?? [], 'label' => 'EQUITY'],
        ];

        foreach ($categories as $category) {
            if (!empty($category['data'])) {
                // Add category header
                $rows[] = [$category['label'], '', '', ''];

                // Process accounts in chunks
                $chunks = array_chunk($category['data'], self::CHUNK_SIZE);

                foreach ($chunks as $chunkIndex => $chunk) {
                    foreach ($chunk as $account) {
                        $rows = array_merge($rows, $this->flattenAccountHierarchy([$account], ''));
                    }

                    $this->manageMemory($chunkIndex);
                }
            }
        }

        return $rows;
    }

    /**
     * Process generic data in chunks
     */
    private function processChunkedGeneric(): array
    {
        $rows = [];
        $data = $this->report->data;

        if (is_array($data) && !empty($data)) {
            $chunks = array_chunk($data, self::CHUNK_SIZE);

            foreach ($chunks as $chunkIndex => $chunk) {
                foreach ($chunk as $item) {
                    if (is_array($item)) {
                        $rows[] = array_values($item);
                    } else {
                        $rows[] = [$item];
                    }
                }

                $this->manageMemory($chunkIndex);
            }
        }

        return $rows;
    }

    /**
     * MEMORY FIX: Manage memory between chunks
     */
    private function manageMemory(int $chunkIndex): void
    {
        $currentMemory = memory_get_usage(true);

        // Force garbage collection every 10 chunks or when memory exceeds limit
        if ($chunkIndex % 10 === 0 || $currentMemory > self::MEMORY_LIMIT) {
            gc_collect_cycles();

            $newMemory = memory_get_usage(true);
            $freed = $currentMemory - $newMemory;

            Log::debug('Memory management executed', [
                'chunk_index' => $chunkIndex,
                'memory_before' => $currentMemory,
                'memory_after' => $newMemory,
                'memory_freed' => $freed,
            ]);

            // If still over limit, log warning
            if ($newMemory > self::MEMORY_LIMIT) {
                Log::warning('Memory usage still high after garbage collection', [
                    'current_memory' => $newMemory,
                    'memory_limit' => self::MEMORY_LIMIT,
                ]);
            }
        }
    }

    /**
     * Transform data for Excel (non-chunked)
     */
    private function transformDataForExcel(array $data): array
    {
        $rows = [];

        if (isset($data['assets'])) {
            // Balance Sheet format
            $rows = array_merge($rows, $this->flattenAccountHierarchy($data['assets'], 'ASSETS'));
            $rows = array_merge($rows, $this->flattenAccountHierarchy($data['liabilities'], 'LIABILITIES'));
            $rows = array_merge($rows, $this->flattenAccountHierarchy($data['equity'], 'EQUITY'));
        } elseif (isset($data['revenues'])) {
            // Income Statement format
            $rows = array_merge($rows, $this->flattenAccountHierarchy($data['revenues'], 'REVENUES'));
            $rows = array_merge($rows, $this->flattenAccountHierarchy($data['expenses'], 'EXPENSES'));
        } elseif (isset($data['members'])) {
            // Member report format
            foreach ($data['members'] as $member) {
                $rows[] = $this->transformMemberData($member);
            }
        }

        return $rows;
    }

    /**
     * Transform member data for Excel
     */
    private function transformMemberData(array $member): array
    {
        return [
            $member['member_number'] ?? '',
            $member['member_name'] ?? '',
            $this->formatNumber($member['savings']['pokok']['ending_balance'] ?? 0),
            $this->formatNumber($member['savings']['wajib']['ending_balance'] ?? 0),
            $this->formatNumber($member['savings']['sukarela']['ending_balance'] ?? 0),
            $this->formatNumber(
                ($member['savings']['pokok']['ending_balance'] ?? 0) +
                    ($member['savings']['wajib']['ending_balance'] ?? 0) +
                    ($member['savings']['sukarela']['ending_balance'] ?? 0)
            ),
        ];
    }

    /**
     * Flatten account hierarchy for Excel
     */
    private function flattenAccountHierarchy(array $accounts, string $category): array
    {
        $rows = [];

        // Add category header if provided
        if ($category) {
            $rows[] = [$category, '', '', ''];
        }

        foreach ($accounts as $account) {
            $rows[] = [
                str_repeat('  ', $account['level'] ?? 0) . ($account['name'] ?? ''),
                $account['code'] ?? '',
                $this->formatNumber($account['balance'] ?? 0),
                $account['type'] ?? ''
            ];

            if (!empty($account['children'])) {
                $rows = array_merge($rows, $this->flattenAccountHierarchy($account['children'], ''));
            }
        }

        return $rows;
    }

    /**
     * Generate headings based on data type
     */
    private function generateHeadings(array $data): array
    {
        if (isset($data['assets'])) {
            return ['Account Name', 'Code', 'Balance', 'Type'];
        } elseif (isset($data['members'])) {
            return ['Member Number', 'Name', 'Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela', 'Total'];
        } elseif (isset($data['revenues'])) {
            return ['Account Name', 'Code', 'Amount', 'Type'];
        }

        return ['Item', 'Value'];
    }

    /**
     * Estimate row count for memory planning
     */
    private function estimateRowCount(): int
    {
        $data = $this->report->data;

        if (isset($data['members'])) {
            return count($data['members']);
        } elseif (isset($data['assets'])) {
            return $this->countAccountRows($data['assets']) +
                $this->countAccountRows($data['liabilities']) +
                $this->countAccountRows($data['equity']) + 3; // Category headers
        }

        return is_array($data) ? count($data) : 0;
    }

    /**
     * Count rows in account hierarchy
     */
    private function countAccountRows(array $accounts): int
    {
        $count = count($accounts);

        foreach ($accounts as $account) {
            if (!empty($account['children'])) {
                $count += $this->countAccountRows($account['children']);
            }
        }

        return $count;
    }

    /**
     * Get last column letter for styling
     */
    private function getLastColumn(): string
    {
        $headings = $this->generateHeadings($this->report->data);
        $columnCount = count($headings);

        return chr(64 + $columnCount); // A=65, so 64+1=A
    }

    /**
     * Get number columns for formatting
     */
    private function getNumberColumns(): string
    {
        $data = $this->report->data;

        if (isset($data['assets']) || isset($data['revenues'])) {
            return 'C:C'; // Balance/Amount column
        } elseif (isset($data['members'])) {
            return 'C:F'; // Savings columns
        }

        return 'B:Z'; // Default to most columns
    }

    /**
     * Format numbers for Excel
     */
    private function formatNumber($value): float
    {
        return is_numeric($value) ? (float) $value : 0;
    }
}

/**
 * Enhanced Summary Sheet
 */
class ReportSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private readonly ReportResultDTO $report,
        private readonly array $options = []
    ) {}

    public function array(): array
    {
        $summary = $this->report->summary;
        $rows = [];

        // Add report metadata
        $rows[] = ['Report Title', $this->report->title];
        $rows[] = ['Generated At', $this->report->generatedAt];
        $rows[] = ['Generated By', $this->report->generatedBy];
        $rows[] = ['Cooperative ID', $this->report->cooperativeId];
        $rows[] = ['', '']; // Empty row

        // Add summary data
        foreach ($summary as $key => $value) {
            $rows[] = [
                ucwords(str_replace('_', ' ', $key)),
                is_numeric($value) ? number_format($value, 2) : $value
            ];
        }

        return $rows;
    }

    public function headings(): array
    {
        return ['Metric', 'Value'];
    }

    public function title(): string
    {
        return 'Summary';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Header row
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '70AD47']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            // Metadata section
            '2:6' => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'E2EFDA']],
            ],
            // Data section
            'A:B' => [
                'alignment' => ['wrapText' => true],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ],
        ];
    }
}

/**
 * Charts Sheet for visual data representation
 */
class ReportChartsSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private readonly ReportResultDTO $report,
        private readonly array $options = []
    ) {}

    public function array(): array
    {
        // Prepare chart data in tabular format
        $rows = [];

        if (isset($this->report->summary['distribution_breakdown'])) {
            $breakdown = $this->report->summary['distribution_breakdown'];

            $rows[] = ['Distribution Type', 'Amount'];
            foreach ($breakdown as $type => $amount) {
                $rows[] = [ucwords(str_replace('_', ' ', $type)), $amount];
            }
        }

        return $rows;
    }

    public function headings(): array
    {
        return []; // Headers included in data
    }

    public function title(): string
    {
        return 'Charts Data';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'FFC000']],
            ],
        ];
    }
}
