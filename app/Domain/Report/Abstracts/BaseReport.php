<?php
// app/Domain/Report/Abstracts/BaseReport.php
namespace App\Domain\Report\Abstracts;

use App\Domain\Report\Contracts\ReportInterface;
use App\Domain\Report\DTOs\ReportParameterDTO;
use App\Domain\Report\DTOs\ReportResultDTO;
use App\Domain\Report\Exceptions\ReportGenerationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SECURITY & PERFORMANCE HARDENED: Base class for all financial reports
 */
abstract class BaseReport implements ReportInterface
{
    protected string $reportCode;
    protected string $reportName;
    protected string $description;
    protected array $requiredParameters = [];
    protected array $supportedFormats = ['html', 'pdf', 'excel', 'csv'];
    protected int $cacheMinutes = 30;
    protected int $timeoutSeconds = 300; // 5 minutes
    protected string $memoryLimit = '512M';

    /**
     * SECURITY & PERFORMANCE FIX: Enhanced report generation with comprehensive error handling
     */
    public function generate(ReportParameterDTO $parameters): ReportResultDTO
    {
        try {
            // Validate parameters
            $this->validateParameters($parameters);

            // Check cache first
            $cacheKey = $parameters->getCacheKey($this->reportCode);

            return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($parameters) {
                // Set resource limits
                $this->setResourceLimits();

                try {
                    // Log report generation start
                    Log::info("Starting report generation: {$this->reportCode}", [
                        'user_id' => auth()->id(),
                        'cooperative_id' => $parameters->cooperativeId,
                        'parameters' => $parameters->toArray(),
                        'memory_start' => memory_get_usage(true),
                    ]);

                    $startTime = microtime(true);

                    // Generate report data with timeout protection
                    $data = $this->generateReportDataWithTimeout($parameters);
                    $summary = $this->generateSummary($data, $parameters);

                    $executionTime = microtime(true) - $startTime;

                    // Log successful generation
                    Log::info("Report generated successfully: {$this->reportCode}", [
                        'execution_time' => $executionTime,
                        'memory_peak' => memory_get_peak_usage(true),
                        'data_rows' => is_array($data) ? count($data) : 0,
                    ]);

                    return new ReportResultDTO(
                        title: $this->getReportTitle($parameters),
                        data: $data,
                        summary: $summary,
                        metadata: array_merge($this->getReportMetadata($parameters), [
                            'execution_time' => $executionTime,
                            'memory_usage' => memory_get_peak_usage(true),
                            'cache_key' => $cacheKey,
                        ]),
                        generatedAt: now()->toISOString(),
                        generatedBy: auth()->user()->name ?? 'System',
                        cooperativeId: $parameters->cooperativeId
                    );
                } catch (\Exception $e) {
                    Log::error("Report generation failed: {$this->reportCode}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'parameters' => $parameters->toArray(),
                        'memory_usage' => memory_get_usage(true),
                    ]);

                    throw new ReportGenerationException(
                        "Failed to generate {$this->reportName}: " . $e->getMessage(),
                        0,
                        $e
                    );
                }
            });
        } catch (ReportGenerationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Unexpected error in report generation", [
                'report_code' => $this->reportCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new ReportGenerationException(
                "An unexpected error occurred while generating the report",
                0,
                $e
            );
        }
    }

    /**
     * SECURITY FIX: Enhanced parameter validation
     */
    public function validateParameters(ReportParameterDTO $parameters): bool
    {
        // Validate cooperative access
        if (!$this->hasCooperativeAccess($parameters->cooperativeId)) {
            throw new \Exception('Access denied to cooperative data');
        }

        // Validate required parameters
        foreach ($this->requiredParameters as $param) {
            if (!$this->hasRequiredParameter($parameters, $param)) {
                throw new \InvalidArgumentException("Required parameter missing: {$param}");
            }
        }

        // Validate user permissions
        if (!$this->hasReportPermission()) {
            throw new \Exception('Insufficient permissions to generate this report');
        }

        return true;
    }

    public function getMetadata(): array
    {
        return [
            'code' => $this->reportCode,
            'name' => $this->reportName,
            'description' => $this->description,
            'required_parameters' => $this->requiredParameters,
            'supported_formats' => $this->supportedFormats,
            'cache_minutes' => $this->cacheMinutes,
            'timeout_seconds' => $this->timeoutSeconds,
        ];
    }

    public function getSupportedFormats(): array
    {
        return $this->supportedFormats;
    }

    /**
     * Abstract methods to be implemented by specific reports
     */
    abstract protected function generateReportData(ReportParameterDTO $parameters): array;
    abstract protected function generateSummary(array $data, ReportParameterDTO $parameters): array;
    abstract protected function getReportTitle(ReportParameterDTO $parameters): string;

    /**
     * PERFORMANCE FIX: Generate report data with timeout protection
     */
    private function generateReportDataWithTimeout(ReportParameterDTO $parameters): array
    {
        $startTime = time();

        // Use read-only transaction for data consistency
        $data = DB::transaction(function () use ($parameters) {
            // Set transaction to read-only for better performance
            DB::statement('SET TRANSACTION READ ONLY');
            return $this->generateReportData($parameters);
        }, 3); // 3 retry attempts

        if (time() - $startTime > $this->timeoutSeconds) {
            throw new \Exception('Report generation timeout exceeded');
        }

        return $data;
    }

    /**
     * Set resource limits for report generation
     */
    private function setResourceLimits(): void
    {
        // Set memory limit
        ini_set('memory_limit', $this->memoryLimit);

        // Set execution time limit
        set_time_limit($this->timeoutSeconds);

        // Disable output buffering for large reports
        if (ob_get_level()) {
            ob_end_clean();
        }
    }

    /**
     * SECURITY FIX: Enhanced cooperative access validation
     */
    protected function hasCooperativeAccess(int $cooperativeId): bool
    {
        $currentTenantId = app(\App\Infrastructure\Tenancy\TenantManager::class)->getCurrentTenantId();
        $user = auth()->user();

        // Basic tenant check
        if ($currentTenantId !== $cooperativeId) {
            return false;
        }

        // Additional validation for user's cooperative membership
        if ($user && $user->cooperative_id !== $cooperativeId) {
            Log::warning('User attempted to access different cooperative data', [
                'user_id' => $user->id,
                'user_cooperative_id' => $user->cooperative_id,
                'requested_cooperative_id' => $cooperativeId,
                'report_code' => $this->reportCode,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Check if user has permission to generate this report
     */
    protected function hasReportPermission(): bool
    {
        $user = auth()->user();

        if (!$user) {
            return false;
        }

        // Check specific report permissions
        $permission = "view-report-{$this->reportCode}";
        if ($user->can($permission)) {
            return true;
        }

        // Check general financial report permission
        if ($user->can('view-financial-reports')) {
            return true;
        }

        Log::warning('Unauthorized report access attempt', [
            'user_id' => $user->id,
            'report_code' => $this->reportCode,
            'required_permission' => $permission,
        ]);

        return false;
    }

    protected function hasRequiredParameter(ReportParameterDTO $parameters, string $param): bool
    {
        return match ($param) {
            'fiscal_period_id' => !is_null($parameters->fiscalPeriodId),
            'member_ids' => !empty($parameters->memberIds),
            'account_ids' => !empty($parameters->accountIds),
            default => true
        };
    }

    protected function getReportMetadata(ReportParameterDTO $parameters): array
    {
        return [
            'report_code' => $this->reportCode,
            'parameters' => $parameters->toArray(),
            'filters_applied' => $this->getAppliedFilters($parameters),
            'total_records' => 0, // Will be set by specific reports
            'generated_by_user' => auth()->user()->name ?? 'System',
            'cooperative_name' => $this->getCooperativeName($parameters->cooperativeId),
        ];
    }

    protected function getAppliedFilters(ReportParameterDTO $parameters): array
    {
        $filters = [];

        if ($parameters->memberIds) {
            $filters['members'] = count($parameters->memberIds) . ' members selected';
        }

        if ($parameters->accountIds) {
            $filters['accounts'] = count($parameters->accountIds) . ' accounts selected';
        }

        if ($parameters->fiscalPeriodId) {
            $filters['fiscal_period'] = 'Specific fiscal period selected';
        }

        return $filters;
    }

    /**
     * Get cooperative name for metadata
     */
    protected function getCooperativeName(int $cooperativeId): string
    {
        $cooperative = DB::table('cooperatives')
            ->where('id', $cooperativeId)
            ->value('name');

        return $cooperative ?? 'Unknown Cooperative';
    }

    /**
     * Performance optimized query builder
     */
    protected function getOptimizedQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::connection('pgsql')
            ->table('journal_entries as je')
            ->join('journal_lines as jl', 'je.id', '=', 'jl.journal_entry_id')
            ->join('accounts as a', 'jl.account_id', '=', 'a.id')
            ->where('je.cooperative_id', app(\App\Infrastructure\Tenancy\TenantManager::class)->getCurrentTenantId())
            ->where('je.is_approved', true);
    }
}
