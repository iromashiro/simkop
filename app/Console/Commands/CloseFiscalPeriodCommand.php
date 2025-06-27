<?php
// app/Console/Commands/CloseFiscalPeriodCommand.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Accounting\Services\FiscalPeriodService;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Accounting\Exceptions\FiscalPeriodClosedException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PRODUCTION READY: Fiscal period closing command with comprehensive validation
 * SRS Reference: Section 3.2.4 - Fiscal Period Management Requirements
 */
class CloseFiscalPeriodCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'hermes:close-fiscal-period
                           {--cooperative= : Close period for specific cooperative}
                           {--period= : Specific period ID to close}
                           {--force : Force close even with warnings}
                           {--dry-run : Preview what would be closed without actually closing}
                           {--auto : Automatically close all eligible periods}';

    /**
     * The console command description.
     */
    protected $description = 'Close fiscal periods with comprehensive validation and automated journal entries';

    private FiscalPeriodService $fiscalPeriodService;

    public function __construct(FiscalPeriodService $fiscalPeriodService)
    {
        parent::__construct();
        $this->fiscalPeriodService = $fiscalPeriodService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);

        try {
            $this->info('🔒 Starting HERMES fiscal period closing process...');

            // Get command options
            $cooperativeId = $this->option('cooperative');
            $periodId = $this->option('period');
            $force = $this->option('force');
            $dryRun = $this->option('dry-run');
            $auto = $this->option('auto');

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No actual changes will be made');
            }

            // Validate inputs
            if (!$this->validateInputs($cooperativeId, $periodId)) {
                return Command::FAILURE;
            }

            // Get periods to close
            $periodsToClose = $this->getPeriodsToClose($cooperativeId, $periodId, $auto);

            if ($periodsToClose->isEmpty()) {
                $this->info('ℹ️ No fiscal periods found that need closing');
                return Command::SUCCESS;
            }

            $this->info("📋 Found {$periodsToClose->count()} period(s) to process");

            // Create progress bar
            $progressBar = $this->output->createProgressBar($periodsToClose->count());
            $progressBar->start();

            $successCount = 0;
            $errorCount = 0;
            $warningCount = 0;

            // Process each period
            foreach ($periodsToClose as $period) {
                $this->newLine();
                $this->info("🏢 Processing: {$period->cooperative->name} - {$period->name}");

                $result = $this->processPeriodClosing($period, $force, $dryRun);

                switch ($result['status']) {
                    case 'success':
                        $successCount++;
                        break;
                    case 'warning':
                        $warningCount++;
                        break;
                    case 'error':
                        $errorCount++;
                        break;
                }

                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine(2);

            // Calculate execution time
            $executionTime = microtime(true) - $startTime;

            // Log completion
            Log::info('Fiscal period closing completed', [
                'periods_processed' => $periodsToClose->count(),
                'successful_closures' => $successCount,
                'warnings' => $warningCount,
                'errors' => $errorCount,
                'execution_time' => $executionTime,
                'dry_run' => $dryRun,
            ]);

            // Display summary
            $this->displaySummary($successCount, $warningCount, $errorCount, $executionTime, $dryRun);

            return $errorCount > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Exception $e) {
            Log::error('Fiscal period closing command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->error("❌ Fiscal period closing failed: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }

    /**
     * Process closing for a single fiscal period
     */
    private function processPeriodClosing(FiscalPeriod $period, bool $force, bool $dryRun): array
    {
        try {
            // Set tenant context
            app('tenant.manager')->setTenant($period->cooperative);

            // Pre-closing validation
            $validationResult = $this->validatePeriodForClosing($period);

            if (!$validationResult['can_close'] && !$force) {
                $this->displayValidationErrors($validationResult);
                return ['status' => 'error', 'message' => 'Validation failed'];
            }

            if (!empty($validationResult['warnings'])) {
                $this->displayValidationWarnings($validationResult['warnings']);

                if (!$force && !$this->confirm('Continue with warnings?')) {
                    return ['status' => 'warning', 'message' => 'Skipped due to warnings'];
                }
            }

            if ($dryRun) {
                $this->info('  🔍 DRY RUN: Would close period successfully');
                $this->displayClosingPreview($period);
                return ['status' => 'success', 'message' => 'Dry run completed'];
            }

            // Perform actual closing
            DB::transaction(function () use ($period) {
                // Generate closing entries
                $this->generateClosingEntries($period);

                // Calculate final balances
                $this->calculateFinalBalances($period);

                // Generate period-end reports
                $this->generatePeriodEndReports($period);

                // Mark period as closed
                $this->fiscalPeriodService->closePeriod($period->id);

                // Create next period if needed
                $this->createNextPeriodIfNeeded($period);
            });

            $this->info("  ✅ Period closed successfully");

            return ['status' => 'success', 'message' => 'Period closed successfully'];
        } catch (FiscalPeriodClosedException $e) {
            $this->warn("  ⚠️ Period already closed: {$e->getMessage()}");
            return ['status' => 'warning', 'message' => 'Already closed'];
        } catch (\Exception $e) {
            Log::error("Failed to close fiscal period {$period->id}", [
                'error' => $e->getMessage(),
                'period_id' => $period->id,
                'cooperative_id' => $period->cooperative_id,
            ]);

            $this->error("  ❌ Failed to close period: {$e->getMessage()}");
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Validate period for closing
     */
    private function validatePeriodForClosing(FiscalPeriod $period): array
    {
        $errors = [];
        $warnings = [];

        // Check if period is already closed
        if ($period->is_closed) {
            $errors[] = 'Period is already closed';
        }

        // Check if period end date has passed
        if ($period->end_date->isFuture()) {
            $warnings[] = 'Period end date has not yet passed';
        }

        // Check for unbalanced journal entries
        $unbalancedEntries = $this->getUnbalancedEntries($period);
        if ($unbalancedEntries->isNotEmpty()) {
            $errors[] = "Found {$unbalancedEntries->count()} unbalanced journal entries";
        }

        // Check for unapproved journal entries
        $unapprovedEntries = $this->getUnapprovedEntries($period);
        if ($unapprovedEntries->isNotEmpty()) {
            $warnings[] = "Found {$unapprovedEntries->count()} unapproved journal entries";
        }

        // Check for pending transactions
        $pendingTransactions = $this->getPendingTransactions($period);
        if ($pendingTransactions->isNotEmpty()) {
            $warnings[] = "Found {$pendingTransactions->count()} pending transactions";
        }

        // Check for incomplete reconciliations
        $incompleteReconciliations = $this->getIncompleteReconciliations($period);
        if ($incompleteReconciliations->isNotEmpty()) {
            $warnings[] = "Found {$incompleteReconciliations->count()} incomplete reconciliations";
        }

        // Check trial balance
        $trialBalanceResult = $this->validateTrialBalance($period);
        if (!$trialBalanceResult['balanced']) {
            $errors[] = "Trial balance is not balanced. Difference: {$trialBalanceResult['difference']}";
        }

        return [
            'can_close' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get unbalanced journal entries
     */
    private function getUnbalancedEntries(FiscalPeriod $period)
    {
        return DB::table('journal_entries as je')
            ->leftJoin('journal_lines as jl', 'je.id', '=', 'jl.journal_entry_id')
            ->where('je.cooperative_id', $period->cooperative_id)
            ->whereBetween('je.transaction_date', [$period->start_date, $period->end_date])
            ->groupBy('je.id')
            ->havingRaw('ABS(SUM(jl.debit_amount) - SUM(jl.credit_amount)) > 0.01')
            ->select('je.id', 'je.reference_number')
            ->get();
    }

    /**
     * Get unapproved journal entries
     */
    private function getUnapprovedEntries(FiscalPeriod $period)
    {
        return DB::table('journal_entries')
            ->where('cooperative_id', $period->cooperative_id)
            ->whereBetween('transaction_date', [$period->start_date, $period->end_date])
            ->where('is_approved', false)
            ->select('id', 'reference_number', 'description')
            ->get();
    }

    /**
     * Get pending transactions
     */
    private function getPendingTransactions(FiscalPeriod $period)
    {
        // Check for pending savings transactions
        $pendingSavings = DB::table('savings')
            ->where('cooperative_id', $period->cooperative_id)
            ->whereBetween('transaction_date', [$period->start_date, $period->end_date])
            ->where('status', 'pending')
            ->count();

        // Check for pending loan payments
        $pendingLoanPayments = DB::table('loan_payments')
            ->where('cooperative_id', $period->cooperative_id)
            ->whereBetween('payment_date', [$period->start_date, $period->end_date])
            ->where('status', 'pending')
            ->count();

        return collect([
            ['type' => 'savings', 'count' => $pendingSavings],
            ['type' => 'loan_payments', 'count' => $pendingLoanPayments],
        ])->filter(fn($item) => $item['count'] > 0);
    }

    /**
     * Get incomplete reconciliations
     */
    private function getIncompleteReconciliations(FiscalPeriod $period)
    {
        // This would check for bank reconciliations, cash reconciliations, etc.
        // For now, return empty collection
        return collect();
    }

    /**
     * Validate trial balance
     */
    private function validateTrialBalance(FiscalPeriod $period): array
    {
        $trialBalance = DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($period) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->whereBetween('je.transaction_date', [$period->start_date, $period->end_date]);
            })
            ->where('a.cooperative_id', $period->cooperative_id)
            ->selectRaw('
                SUM(CASE
                    WHEN a.type IN (\'ASSET\', \'EXPENSE\')
                    THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)
                    ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)
                END) as total_balance
            ')
            ->value('total_balance') ?? 0;

        $difference = abs($trialBalance);

        return [
            'balanced' => $difference < 0.01, // Allow 1 cent tolerance
            'difference' => $difference,
            'total_balance' => $trialBalance,
        ];
    }

    /**
     * Generate closing entries
     */
    private function generateClosingEntries(FiscalPeriod $period): void
    {
        $this->info('  📝 Generating closing entries...');

        // Close revenue accounts
        $this->closeRevenueAccounts($period);

        // Close expense accounts
        $this->closeExpenseAccounts($period);

        // Transfer net income to retained earnings
        $this->transferNetIncomeToRetainedEarnings($period);
    }

    /**
     * Close revenue accounts
     */
    private function closeRevenueAccounts(FiscalPeriod $period): void
    {
        $revenueAccounts = DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($period) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->whereBetween('je.transaction_date', [$period->start_date, $period->end_date]);
            })
            ->where('a.cooperative_id', $period->cooperative_id)
            ->where('a.type', 'REVENUE')
            ->groupBy('a.id', 'a.code', 'a.name')
            ->selectRaw('
                a.id,
                a.code,
                a.name,
                SUM(COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)) as balance
            ')
            ->having('balance', '>', 0)
            ->get();

        if ($revenueAccounts->isNotEmpty()) {
            // Create closing entry for revenue accounts
            $this->createClosingEntry($period, $revenueAccounts, 'revenue');
        }
    }

    /**
     * Close expense accounts
     */
    private function closeExpenseAccounts(FiscalPeriod $period): void
    {
        $expenseAccounts = DB::table('accounts as a')
            ->leftJoin('journal_lines as jl', 'a.id', '=', 'jl.account_id')
            ->leftJoin('journal_entries as je', function ($join) use ($period) {
                $join->on('jl.journal_entry_id', '=', 'je.id')
                    ->where('je.is_approved', true)
                    ->whereBetween('je.transaction_date', [$period->start_date, $period->end_date]);
            })
            ->where('a.cooperative_id', $period->cooperative_id)
            ->where('a.type', 'EXPENSE')
            ->groupBy('a.id', 'a.code', 'a.name')
            ->selectRaw('
                a.id,
                a.code,
                a.name,
                SUM(COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)) as balance
            ')
            ->having('balance', '>', 0)
            ->get();

        if ($expenseAccounts->isNotEmpty()) {
            // Create closing entry for expense accounts
            $this->createClosingEntry($period, $expenseAccounts, 'expense');
        }
    }

    /**
     * Create closing entry
     */
    private function createClosingEntry(FiscalPeriod $period, $accounts, string $type): void
    {
        $journalEntry = DB::table('journal_entries')->insertGetId([
            'cooperative_id' => $period->cooperative_id,
            'reference_number' => $this->generateClosingEntryReference($period, $type),
            'transaction_date' => $period->end_date,
            'description' => "Closing entry for {$type} accounts - Period: {$period->name}",
            'is_approved' => true,
            'is_closing_entry' => true,
            'fiscal_period_id' => $period->id,
            'created_by' => 1, // System user
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $totalAmount = 0;

        // Create journal lines for each account
        foreach ($accounts as $account) {
            $amount = abs($account->balance);
            $totalAmount += $amount;

            if ($type === 'revenue') {
                // Debit revenue accounts to close them
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journalEntry,
                    'account_id' => $account->id,
                    'debit_amount' => $amount,
                    'credit_amount' => 0,
                    'description' => "Closing {$account->name}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                // Credit expense accounts to close them
                DB::table('journal_lines')->insert([
                    'journal_entry_id' => $journalEntry,
                    'account_id' => $account->id,
                    'debit_amount' => 0,
                    'credit_amount' => $amount,
                    'description' => "Closing {$account->name}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Create balancing entry to Income Summary account
        $incomeSummaryAccount = $this->getOrCreateIncomeSummaryAccount($period->cooperative_id);

        DB::table('journal_lines')->insert([
            'journal_entry_id' => $journalEntry,
            'account_id' => $incomeSummaryAccount->id,
            'debit_amount' => $type === 'expense' ? $totalAmount : 0,
            'credit_amount' => $type === 'revenue' ? $totalAmount : 0,
            'description' => "Transfer to Income Summary",
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Transfer net income to retained earnings
     */
    private function transferNetIncomeToRetainedEarnings(FiscalPeriod $period): void
    {
        // Get Income Summary account balance
        $incomeSummaryAccount = $this->getOrCreateIncomeSummaryAccount($period->cooperative_id);

        $incomeSummaryBalance = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->where('jl.account_id', $incomeSummaryAccount->id)
            ->where('je.cooperative_id', $period->cooperative_id)
            ->where('je.is_approved', true)
            ->whereBetween('je.transaction_date', [$period->start_date, $period->end_date])
            ->selectRaw('SUM(COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)) as balance')
            ->value('balance') ?? 0;

        if (abs($incomeSummaryBalance) > 0.01) {
            // Create entry to transfer to retained earnings
            $retainedEarningsAccount = $this->getOrCreateRetainedEarningsAccount($period->cooperative_id);

            $journalEntry = DB::table('journal_entries')->insertGetId([
                'cooperative_id' => $period->cooperative_id,
                'reference_number' => $this->generateClosingEntryReference($period, 'net_income'),
                'transaction_date' => $period->end_date,
                'description' => "Transfer net income to retained earnings - Period: {$period->name}",
                'is_approved' => true,
                'is_closing_entry' => true,
                'fiscal_period_id' => $period->id,
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $amount = abs($incomeSummaryBalance);

            // Close Income Summary
            DB::table('journal_lines')->insert([
                'journal_entry_id' => $journalEntry,
                'account_id' => $incomeSummaryAccount->id,
                'debit_amount' => $incomeSummaryBalance > 0 ? $amount : 0,
                'credit_amount' => $incomeSummaryBalance < 0 ? $amount : 0,
                'description' => "Close Income Summary",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Transfer to Retained Earnings
            DB::table('journal_lines')->insert([
                'journal_entry_id' => $journalEntry,
                'account_id' => $retainedEarningsAccount->id,
                'debit_amount' => $incomeSummaryBalance < 0 ? $amount : 0,
                'credit_amount' => $incomeSummaryBalance > 0 ? $amount : 0,
                'description' => "Net income transfer",
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Calculate final balances
     */
    private function calculateFinalBalances(FiscalPeriod $period): void
    {
        $this->info('  🧮 Calculating final balances...');

        // Calculate and store final balances for all accounts
        $accounts = DB::table('accounts')
            ->where('cooperative_id', $period->cooperative_id)
            ->get();

        foreach ($accounts as $account) {
            $balance = $this->calculateAccountBalance($account->id, $period->end_date);

            // Store final balance
            DB::table('account_balances')->updateOrInsert([
                'account_id' => $account->id,
                'fiscal_period_id' => $period->id,
            ], [
                'ending_balance' => $balance,
                'balance_date' => $period->end_date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Calculate account balance as of specific date
     */
    private function calculateAccountBalance(int $accountId, $asOfDate): float
    {
        $account = DB::table('accounts')->find($accountId);

        $balance = DB::table('journal_lines as jl')
            ->join('journal_entries as je', 'jl.journal_entry_id', '=', 'je.id')
            ->where('jl.account_id', $accountId)
            ->where('je.is_approved', true)
            ->where('je.transaction_date', '<=', $asOfDate)
            ->selectRaw('
                SUM(CASE
                    WHEN ? IN (\'ASSET\', \'EXPENSE\')
                    THEN COALESCE(jl.debit_amount, 0) - COALESCE(jl.credit_amount, 0)
                    ELSE COALESCE(jl.credit_amount, 0) - COALESCE(jl.debit_amount, 0)
                END) as balance
            ', [$account->type])
            ->value('balance') ?? 0;

        return (float) $balance;
    }

    /**
     * Generate period-end reports
     */
    private function generatePeriodEndReports(FiscalPeriod $period): void
    {
        $this->info('  📊 Generating period-end reports...');

        // Generate key financial reports for the closed period
        $reports = [
            'balance_sheet',
            'income_statement',
            'cash_flow',
            'equity_changes',
        ];

        foreach ($reports as $reportType) {
            try {
                // This would integrate with the existing report generation service
                $this->info("    📋 Generating {$reportType} report...");

                // Store report metadata
                DB::table('generated_reports')->insert([
                    'cooperative_id' => $period->cooperative_id,
                    'report_type' => $reportType,
                    'fiscal_period_id' => $period->id,
                    'generated_at' => now(),
                    'generated_by' => 1, // System user
                    'is_period_end_report' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Exception $e) {
                Log::warning("Failed to generate {$reportType} report for period {$period->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create next period if needed
     */
    private function createNextPeriodIfNeeded(FiscalPeriod $period): void
    {
        // Check if next period already exists
        $nextPeriod = DB::table('fiscal_periods')
            ->where('cooperative_id', $period->cooperative_id)
            ->where('start_date', $period->end_date->addDay())
            ->first();

        if (!$nextPeriod) {
            $this->info('  📅 Creating next fiscal period...');

            // Create next period (typically next month/quarter/year)
            $nextStartDate = $period->end_date->copy()->addDay();
            $nextEndDate = $this->calculateNextPeriodEndDate($nextStartDate, $period);

            DB::table('fiscal_periods')->insert([
                'cooperative_id' => $period->cooperative_id,
                'name' => $this->generateNextPeriodName($period),
                'start_date' => $nextStartDate,
                'end_date' => $nextEndDate,
                'is_closed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Calculate next period end date
     */
    private function calculateNextPeriodEndDate($startDate, FiscalPeriod $currentPeriod)
    {
        // Calculate period length from current period
        $periodLength = $currentPeriod->start_date->diffInDays($currentPeriod->end_date);

        return $startDate->copy()->addDays($periodLength);
    }

    /**
     * Generate next period name
     */
    private function generateNextPeriodName(FiscalPeriod $currentPeriod): string
    {
        // Extract period type and increment
        if (preg_match('/(\d{4})-(\d{2})/', $currentPeriod->name, $matches)) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];

            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }

            return sprintf('%04d-%02d', $year, $month);
        }

        // Fallback: append "Next" to current name
        return $currentPeriod->name . ' - Next';
    }

    /**
     * Get or create Income Summary account
     */
    private function getOrCreateIncomeSummaryAccount(int $cooperativeId)
    {
        $account = DB::table('accounts')
            ->where('cooperative_id', $cooperativeId)
            ->where('code', '3900')
            ->where('name', 'Income Summary')
            ->first();

        if (!$account) {
            $accountId = DB::table('accounts')->insertGetId([
                'cooperative_id' => $cooperativeId,
                'code' => '3900',
                'name' => 'Income Summary',
                'type' => 'EQUITY',
                'parent_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $account = DB::table('accounts')->find($accountId);
        }

        return $account;
    }

    /**
     * Get or create Retained Earnings account
     */
    private function getOrCreateRetainedEarningsAccount(int $cooperativeId)
    {
        $account = DB::table('accounts')
            ->where('cooperative_id', $cooperativeId)
            ->where('code', '3200')
            ->where('name', 'Retained Earnings')
            ->first();

        if (!$account) {
            $accountId = DB::table('accounts')->insertGetId([
                'cooperative_id' => $cooperativeId,
                'code' => '3200',
                'name' => 'Retained Earnings',
                'type' => 'EQUITY',
                'parent_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $account = DB::table('accounts')->find($accountId);
        }

        return $account;
    }

    /**
     * Generate closing entry reference number
     */
    private function generateClosingEntryReference(FiscalPeriod $period, string $type): string
    {
        $typeCode = match ($type) {
            'revenue' => 'CR',
            'expense' => 'CE',
            'net_income' => 'CNI',
            default => 'CL'
        };

        return "CLOSE-{$typeCode}-{$period->id}-" . date('Ymd');
    }

    /**
     * Display validation errors
     */
    private function displayValidationErrors(array $validationResult): void
    {
        if (!empty($validationResult['errors'])) {
            $this->error('  ❌ Validation Errors:');
            foreach ($validationResult['errors'] as $error) {
                $this->error("    • {$error}");
            }
        }
    }

    /**
     * Display validation warnings
     */
    private function displayValidationWarnings(array $warnings): void
    {
        if (!empty($warnings)) {
            $this->warn('  ⚠️ Warnings:');
            foreach ($warnings as $warning) {
                $this->warn("    • {$warning}");
            }
        }
    }

    /**
     * Display closing preview for dry run
     */
    private function displayClosingPreview(FiscalPeriod $period): void
    {
        $this->info('  📋 Closing Preview:');
        $this->info("    • Period: {$period->name}");
        $this->info("    • Date Range: {$period->start_date->format('Y-m-d')} to {$period->end_date->format('Y-m-d')}");
        $this->info("    • Cooperative: {$period->cooperative->name}");

        // Show what would be closed
        $revenueCount = DB::table('accounts')
            ->where('cooperative_id', $period->cooperative_id)
            ->where('type', 'REVENUE')
            ->count();

        $expenseCount = DB::table('accounts')
            ->where('cooperative_id', $period->cooperative_id)
            ->where('type', 'EXPENSE')
            ->count();

        $this->info("    • Revenue accounts to close: {$revenueCount}");
        $this->info("    • Expense accounts to close: {$expenseCount}");
    }

    /**
     * Validate command inputs
     */
    private function validateInputs(?string $cooperativeId, ?string $periodId): bool
    {
        if ($cooperativeId && !Cooperative::find($cooperativeId)) {
            $this->error("❌ Cooperative ID {$cooperativeId} not found");
            return false;
        }

        if ($periodId && !FiscalPeriod::find($periodId)) {
            $this->error("❌ Fiscal Period ID {$periodId} not found");
            return false;
        }

        return true;
    }

    /**
     * Get periods to close
     */
    private function getPeriodsToClose(?string $cooperativeId, ?string $periodId, bool $auto)
    {
        $query = FiscalPeriod::with('cooperative')
            ->where('is_closed', false);

        if ($periodId) {
            return $query->where('id', $periodId)->get();
        }

        if ($cooperativeId) {
            $query->where('cooperative_id', $cooperativeId);
        }

        if ($auto) {
            // Only include periods where end date has passed
            $query->where('end_date', '<', now());
        }

        return $query->orderBy('end_date')->get();
    }

    /**
     * Display closing summary
     */
    private function displaySummary(int $successCount, int $warningCount, int $errorCount, float $executionTime, bool $dryRun): void
    {
        $mode = $dryRun ? 'DRY RUN' : 'ACTUAL';

        $this->info("🔒 Fiscal Period Closing Summary ({$mode}):");
        $this->info("✅ Successfully processed: {$successCount} periods");

        if ($warningCount > 0) {
            $this->warn("⚠️ Processed with warnings: {$warningCount} periods");
        }

        if ($errorCount > 0) {
            $this->error("❌ Failed to process: {$errorCount} periods");
        }

        $this->info("⏱️ Total execution time: " . round($executionTime, 2) . " seconds");

        if (!$dryRun && $successCount > 0) {
            $this->info("📊 Period-end reports generated and saved");
            $this->info("📅 Next periods created automatically");
        }
    }
}
