<?php
// app/Domain/Report/Drivers/CsvExportDriver.php
namespace App\Domain\Report\Drivers;

use App\Domain\Report\Contracts\ExportDriverInterface;
use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\DTOs\ExportRequestDTO;
use App\Domain\Report\DTOs\ExportResultDTO;

/**
 * CSV Export Driver
 */
class CsvExportDriver implements ExportDriverInterface
{
    public function export(ReportResultDTO $report, ExportRequestDTO $request): ExportResultDTO
    {
        $startTime = microtime(true);

        // Transform data to CSV format
        $csvData = $this->transformToCsv($report->data);

        // Generate CSV content
        $content = $this->generateCsvContent($csvData, $request->options);
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
            'delimiter' => [',', ';', '\t'],
            'enclosure' => ['"', "'"],
            'include_headers' => 'boolean',
            'encoding' => ['UTF-8', 'ISO-8859-1'],
        ];
    }

    public function getMimeType(): string
    {
        return 'text/csv';
    }

    public function getFileExtension(): string
    {
        return 'csv';
    }

    private function transformToCsv(array $data): array
    {
        $rows = [];

        // Add headers
        if (isset($data['assets'])) {
            $rows[] = ['Account Name', 'Code', 'Balance', 'Type'];
            $rows = array_merge($rows, $this->flattenAccountsForCsv($data['assets']));
            $rows = array_merge($rows, $this->flattenAccountsForCsv($data['liabilities']));
            $rows = array_merge($rows, $this->flattenAccountsForCsv($data['equity']));
        } elseif (isset($data['members'])) {
            $rows[] = ['Member Number', 'Name', 'Simpanan Pokok', 'Simpanan Wajib', 'Simpanan Sukarela', 'Total'];
            foreach ($data['members'] as $member) {
                $rows[] = [
                    $member['member_number'],
                    $member['member_name'],
                    $member['savings']['pokok']['ending_balance'] ?? 0,
                    $member['savings']['wajib']['ending_balance'] ?? 0,
                    $member['savings']['sukarela']['ending_balance'] ?? 0,
                    ($member['savings']['pokok']['ending_balance'] ?? 0) +
                        ($member['savings']['wajib']['ending_balance'] ?? 0) +
                        ($member['savings']['sukarela']['ending_balance'] ?? 0),
                ];
            }
        }

        return $rows;
    }

    private function flattenAccountsForCsv(array $accounts): array
    {
        $rows = [];
        foreach ($accounts as $account) {
            $rows[] = [
                $account['name'],
                $account['code'],
                $account['balance'],
                $account['type']
            ];

            if (!empty($account['children'])) {
                $rows = array_merge($rows, $this->flattenAccountsForCsv($account['children']));
            }
        }
        return $rows;
    }

    private function generateCsvContent(array $data, array $options): string
    {
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';

        $output = fopen('php://temp', 'r+');

        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter, $enclosure);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    private function generateFilename(ReportResultDTO $report): string
    {
        $title = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $report->title);
        return $title . '_' . now()->format('Y_m_d_H_i_s');
    }
}
