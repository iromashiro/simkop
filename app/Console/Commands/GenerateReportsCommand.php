<?php
// app/Console/Commands/GenerateReportsCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Reporting\Services\ReportGenerationService;
use App\Domain\Reporting\DTOs\ReportParametersDTO;
use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * PRODUCTION READY: Automated report generation command
 * SRS Reference: Section 3.4 - Automated Report Generation Requirements
 */
class GenerateReportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hermes:generate-reports
                           {--cooperative= : Generate reports for specific cooperative}
                           {--report= : Generate specific report type}
                           {--period= : Report period (monthly, quarterly, yearly)}
                           {--format=pdf : Output format (pdf, excel, csv)}
                           {--email : Email reports to cooperative admins}
                           {--schedule : Run as scheduled task}';

    /**
     * The console command description.
     */
    protected $description = 'Generate financial reports automatically for cooperatives';

    private ReportGenerationService $reportService;

    public function __construct(ReportGenerationService $reportService)
    {
        parent::__construct();
        $this->reportService = $reportService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        try {
            $this->info('📊 Starting HERMES automated report generation...');

            // Get command options
            $cooperativeId = $this->option('cooperative');
            $reportType = $this->option('report');
            $period = $this->option('period') ?? 'monthly';
            $format = $this->option('format');
            $shouldEmail = $this->option('email');
            $isScheduled = $this->option('schedule');

            // Validate inputs
            if (!$this->validateInputs($cooperativeId, $reportType, $period, $format)) {
                return Command::FAILURE;
            }

            // Get cooperatives to process
            $cooperatives = $this->getCooperativesToProcess($cooperativeId);

            if ($cooperatives->isEmpty()) {
                $this->warn('⚠️ No cooperatives found to process');
                return Command::SUCCESS;
            }

            $this->info("🏢 Processing {$cooperatives->count()} cooperative(s)");

            // Create progress bar
            $totalReports = $this->calculateTotalReports($cooperatives, $reportType);
            $progressBar = $this->output->createProgressBar($totalReports);
            $progressBar->start();

            $successCount = 0;
            $errorCount = 0;
            $generatedReports = [];

            // Process each cooperative
            foreach ($cooperatives as $cooperative) {
                $this->info("\n🏢 Processing: {$cooperative->name}");

                $cooperativeResults = $this->processCooperative(
                    $cooperative,
                    $reportType,
                    $period,
                    $format,
                    $progressBar
                );

                $successCount += $cooperativeResults['success'];
                $errorCount += $cooperativeResults['errors'];
                $generatedReports = array_merge($generatedReports, $cooperativeResults['reports']);
            }

            $progressBar->finish();
            $this->newLine(2);

            // Email reports if requested
            if ($shouldEmail && !empty($generatedReports)) {
                $this->info('📧 Sending reports via email...');
                $this->emailReports($generatedReports);
            }

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            // Log completion
            Log::info('Automated report generation completed', [
                'cooperatives_processed' => $cooperatives->count(),
                'reports_generated' => $successCount,
                'errors' => $errorCount,
                'execution_time' => $executionTime,
                'period' => $period,
                'format' => $format,
                'scheduled' => $isScheduled,
            ]);

            // Display summary
            $this->displaySummary($successCount, $errorCount, $executionTime);

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Report generation command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("❌ Report generation failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Process reports for a single cooperative
     */
    private function processCooperative(
        Cooperative $cooperative,
        ?string $reportType,
        string $period,
        string $format,
        $progressBar
    ): array {
        $results = [
            'success' => 0,
            'errors' => 0,
            'reports' => [],
        ];

        try {
            // Set tenant context
            app('tenant.manager')->setTenant($cooperative);

            // Get reports to generate
            $reportsToGenerate = $this->getReportsToGenerate($reportType);

            foreach ($reportsToGenerate as $report) {
                try {
                    $this->info("  📋 Generating {$report} report...");

                    // Create report parameters
                    $parameters = $this->createReportParameters($report, $period, $cooperative->id);

                    // Generate report
                    $reportResult = $this->reportService->generateReport($parameters);

                    // Save report file
                    $filePath = $this->saveReportFile($reportResult, $format, $cooperative, $report, $period);

                    $results['reports'][] = [
                        'cooperative' => $cooperative,
                        'report_type' => $report,
                        'file_path' => $filePath,
                        'format' => $format,
                        'period' => $period,
                    ];

                    $results['success']++;
                    $progressBar->advance();

                    $this->info("    ✅ {$report} report generated successfully");
                } catch (\Exception $e) {
                    $results['errors']++;
                    $progressBar->advance();

                    Log::error("Failed to generate {$report} report for cooperative {$cooperative->id}", [
                        'error' => $e->getMessage(),
                        'cooperative_id' => $cooperative->id,
                        'report_type' => $report,
                    ]);

                    $this->error("    ❌ Failed to generate {$report} report: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to process cooperative {$cooperative->id}", [
                'error' => $e->getMessage(),
                'cooperative_id' => $cooperative->id,
            ]);

            $this->error("❌ Failed to process cooperative {$cooperative->name}: {$e->getMessage()}");
        }

        return $results;
    }

    /**
     * Create report parameters DTO
     */
    private function createReportParameters(string $reportType, string $period, int $cooperativeId): ReportParametersDTO
    {
        // Calculate date range based on period
        $dateRange = $this->calculateDateRange($period);

        return new ReportParametersDTO(
            reportType: $reportType,
            cooperativeId: $cooperativeId,
            startDate: $dateRange['start'],
            endDate: $dateRange['end'],
            parameters: [
                'period' => $period,
                'generated_by' => 'system',
                'automated' => true,
            ]
        );
    }

    /**
     * Calculate date range for period
     */
    private function calculateDateRange(string $period): array
    {
        $now = now();

        return match ($period) {
            'monthly' => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ],
            'quarterly' => [
                'start' => $now->copy()->subQuarter()->startOfQuarter(),
                'end' => $now->copy()->subQuarter()->endOfQuarter(),
            ],
            'yearly' => [
                'start' => $now->copy()->subYear()->startOfYear(),
                'end' => $now->copy()->subYear()->endOfYear(),
            ],
            'current_month' => [
                'start' => $now->copy()->startOfMonth(),
                'end' => $now->copy()->endOfMonth(),
            ],
            'current_quarter' => [
                'start' => $now->copy()->startOfQuarter(),
                'end' => $now->copy()->endOfQuarter(),
            ],
            'current_year' => [
                'start' => $now->copy()->startOfYear(),
                'end' => $now->copy()->endOfYear(),
            ],
            default => [
                'start' => $now->copy()->subMonth()->startOfMonth(),
                'end' => $now->copy()->subMonth()->endOfMonth(),
            ]
        };
    }

    /**
     * Save report file to storage
     */
    private function saveReportFile($reportResult, string $format, Cooperative $cooperative, string $reportType, string $period): string
    {
        // Generate filename
        $timestamp = now()->format('Y-m-d_H-i-s');
        $filename = "reports/{$cooperative->id}/{$period}/{$reportType}_{$timestamp}.{$format}";

        // Save file based on format
        switch ($format) {
            case 'pdf':
                $content = $this->generatePdfContent($reportResult);
                break;
            case 'excel':
                $content = $this->generateExcelContent($reportResult);
                break;
            case 'csv':
                $content = $this->generateCsvContent($reportResult);
                break;
            default:
                throw new \InvalidArgumentException("Unsupported format: {$format}");
        }

        // Store file
        Storage::disk('local')->put($filename, $content);

        return $filename;
    }

    /**
     * Generate PDF content
     */
    private function generatePdfContent($reportResult): string
    {
        // Use existing PDF generation logic
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('reports.pdf.template', ['report' => $reportResult]);
        return $pdf->output();
    }

    /**
     * Generate Excel content
     */
    private function generateExcelContent($reportResult): string
    {
        // Use existing Excel generation logic
        return \Excel::raw(new \App\Exports\ReportExport($reportResult), \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * Generate CSV content
     */
    private function generateCsvContent($reportResult): string
    {
        $output = fopen('php://temp', 'r+');

        // Add headers
        $headers = $this->getCsvHeaders($reportResult);
        fputcsv($output, $headers);

        // Add data rows
        $rows = $this->getCsvRows($reportResult);
        foreach ($rows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);

        return $content;
    }

    /**
     * Get CSV headers for report
     */
    private function getCsvHeaders($reportResult): array
    {
        // Return appropriate headers based on report type
        return ['Item', 'Value', 'Description'];
    }

    /**
     * Get CSV rows for report
     */
    private function getCsvRows($reportResult): array
    {
        $rows = [];

        // Convert report data to CSV rows
        if (isset($reportResult->data)) {
            foreach ($reportResult->data as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $subKey => $subValue) {
                        $rows[] = [$key . ' - ' . $subKey, $subValue, ''];
                    }
                } else {
                    $rows[] = [$key, $value, ''];
                }
            }
        }

        return $rows;
    }

    /**
     * Email reports to cooperative admins
     */
    private function emailReports(array $reports): void
    {
        foreach ($reports as $reportData) {
            try {
                $cooperative = $reportData['cooperative'];

                // Get cooperative admins
                $admins = $cooperative->users()
                    ->whereHas('roles', function ($query) {
                        $query->where('name', 'cooperative_admin');
                    })
                    ->get();

                foreach ($admins as $admin) {
                    // Send email with report attachment
                    \Mail::to($admin->email)->send(
                        new \App\Mail\AutomatedReportMail($reportData)
                    );
                }

                $this->info("  📧 Report emailed to {$cooperative->name} admins");
            } catch (\Exception $e) {
                Log::error('Failed to email report', [
                    'error' => $e->getMessage(),
                    'report' => $reportData,
                ]);

                $this->error("  ❌ Failed to email report: {$e->getMessage()}");
            }
        }
    }

    /**
     * Validate command inputs
     */
    private function validateInputs(?string $cooperativeId, ?string $reportType, string $period, string $format): bool
    {
        // Validate cooperative ID
        if ($cooperativeId && !Cooperative::find($cooperativeId)) {
            $this->error("❌ Cooperative ID {$cooperativeId} not found");
            return false;
        }

        // Validate report type
        if ($reportType && !in_array($reportType, $this->getAvailableReports())) {
            $this->error("❌ Invalid report type: {$reportType}");
            $this->info("Available reports: " . implode(', ', $this->getAvailableReports()));
            return false;
        }

        // Validate period
        if (!in_array($period, ['monthly', 'quarterly', 'yearly', 'current_month', 'current_quarter', 'current_year'])) {
            $this->error("❌ Invalid period: {$period}");
            return false;
        }

        // Validate format
        if (!in_array($format, ['pdf', 'excel', 'csv'])) {
            $this->error("❌ Invalid format: {$format}");
            return false;
        }

        return true;
    }

    /**
     * Get cooperatives to process
     */
    private function getCooperativesToProcess(?string $cooperativeId)
    {
        if ($cooperativeId) {
            return Cooperative::where('id', $cooperativeId)->get();
        }

        return Cooperative::where('status', 'active')->get();
    }

    /**
     * Get reports to generate
     */
    private function getReportsToGenerate(?string $reportType): array
    {
        if ($reportType) {
            return [$reportType];
        }

        // Return all available reports
        return $this->getAvailableReports();
    }

    /**
     * Get available report types
     */
    private function getAvailableReports(): array
    {
        return [
            'balance_sheet',
            'income_statement',
            'cash_flow',
            'equity_changes',
            'financial_notes',
            'member_savings',
            'loan_receivables',
            'non_performing_loans',
            'shu_distribution',
            'budget_variance',
        ];
    }

    /**
     * Calculate total reports to generate
     */
    private function calculateTotalReports($cooperatives, ?string $reportType): int
    {
        $reportsPerCooperative = $reportType ? 1 : count($this->getAvailableReports());
        return $cooperatives->count() * $reportsPerCooperative;
    }

    /**
     * Display generation summary
     */
    private function displaySummary(int $successCount, int $errorCount, float $executionTime): void
    {
        $this->info('📊 Report Generation Summary:');
        $this->info("✅ Successfully generated: {$successCount} reports");

        if ($errorCount > 0) {
            $this->error("❌ Failed to generate: {$errorCount} reports");
        }

        $this->info("⏱️ Total execution time: " . round($executionTime, 2) . " seconds");
        $this->info("📁 Reports saved to: storage/app/reports/");
    }
}
