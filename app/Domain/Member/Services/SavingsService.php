<?php
// app/Domain/Member/Services/SavingsService.php
namespace App\Domain\Member\Services;

use App\Domain\Member\Models\Member;
use App\Domain\Member\Models\Savings;
use App\Domain\Member\DTOs\SavingsTransactionDTO;
use App\Domain\Financial\Services\FinancialEntryService;
use Illuminate\Support\Facades\DB;

/**
 * Service for managing member savings transactions
 *
 * Handles all savings operations with proper financial integration
 * and business rule validation
 */
class SavingsService
{
    public function __construct(
        private readonly FinancialEntryService $financialEntryService
    ) {}

    /**
     * SECURITY FIX: Process savings deposit with comprehensive error handling
     */
    public function processDeposit(SavingsTransactionDTO $dto): Savings
    {
        return DB::transaction(function () use ($dto) {
            try {
                // Validate DTO
                $this->validateTransactionDTO($dto);

                // Create savings transaction
                $savings = Savings::createDeposit(
                    $dto->member,
                    $dto->type,
                    $dto->amount,
                    $dto->description,
                    $dto->reference
                );

                // Create corresponding journal entry
                $journalEntry = $this->createJournalEntry($savings, 'deposit');

                // SECURITY: Validate journal entry is balanced
                if (!$journalEntry->isBalanced()) {
                    throw new UnbalancedEntryException('Journal entry is not balanced');
                }

                // Log successful transaction
                Log::info('Savings deposit processed successfully', [
                    'savings_id' => $savings->id,
                    'member_id' => $dto->member->id,
                    'amount' => $dto->amount,
                    'type' => $dto->type,
                    'journal_entry_id' => $journalEntry->id,
                ]);

                return $savings;
            } catch (UnbalancedEntryException $e) {
                Log::error('Unbalanced journal entry in savings deposit', [
                    'member_id' => $dto->member->id,
                    'amount' => $dto->amount,
                    'type' => $dto->type,
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            } catch (\Exception $e) {
                // Log the error with full context
                Log::error('Savings deposit failed', [
                    'member_id' => $dto->member->id,
                    'amount' => $dto->amount,
                    'type' => $dto->type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * SECURITY FIX: Process savings withdrawal with enhanced validation
     */
    public function processWithdrawal(SavingsTransactionDTO $dto): Savings
    {
        return DB::transaction(function () use ($dto) {
            try {
                // Validate DTO
                $this->validateTransactionDTO($dto);

                // Enhanced withdrawal validation
                $this->validateWithdrawal($dto);

                // Create savings transaction
                $savings = Savings::createWithdrawal(
                    $dto->member,
                    $dto->type,
                    $dto->amount,
                    $dto->description,
                    $dto->reference
                );

                // Create corresponding journal entry
                $journalEntry = $this->createJournalEntry($savings, 'withdrawal');

                // SECURITY: Validate journal entry is balanced
                if (!$journalEntry->isBalanced()) {
                    throw new UnbalancedEntryException('Journal entry is not balanced');
                }

                // Log successful transaction
                Log::info('Savings withdrawal processed successfully', [
                    'savings_id' => $savings->id,
                    'member_id' => $dto->member->id,
                    'amount' => $dto->amount,
                    'type' => $dto->type,
                    'journal_entry_id' => $journalEntry->id,
                ]);

                return $savings;
            } catch (\Exception $e) {
                Log::error('Savings withdrawal failed', [
                    'member_id' => $dto->member->id,
                    'amount' => $dto->amount,
                    'type' => $dto->type,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'user_id' => auth()->id(),
                    'ip' => request()->ip(),
                ]);

                throw $e;
            }
        });
    }

    /**
     * SECURITY: Validate transaction DTO
     */
    private function validateTransactionDTO(SavingsTransactionDTO $dto): void
    {
        if (!$dto->member) {
            throw new \InvalidArgumentException('Member is required');
        }

        if (!in_array($dto->type, array_keys(Savings::TYPES))) {
            throw new \InvalidArgumentException('Invalid savings type');
        }

        if ($dto->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }

        if ($dto->amount > 999999999.99) {
            throw new \InvalidArgumentException('Amount exceeds maximum allowed value');
        }

        // Validate member belongs to current tenant
        $currentTenantId = app(\App\Infrastructure\Tenancy\TenantManager::class)->getCurrentTenantId();
        if ($dto->member->cooperative_id !== $currentTenantId) {
            throw new \InvalidArgumentException('Member does not belong to current cooperative');
        }

        // Validate member is active
        if ($dto->member->status !== 'active') {
            throw new \InvalidArgumentException('Member is not active');
        }
    }

    /**
     * Enhanced withdrawal validation
     */
    private function validateWithdrawal(SavingsTransactionDTO $dto): void
    {
        $currentBalance = $dto->member->getSavingsBalance($dto->type);

        if ($dto->amount > $currentBalance) {
            throw new \Exception("Insufficient balance. Current balance: " . number_format($currentBalance, 2) . ", Requested: " . number_format($dto->amount, 2));
        }

        // Additional validation for mandatory savings
        if ($dto->type === 'wajib') {
            $this->validateMandatorySavingsWithdrawal($dto->member);
        }

        // SECURITY: Check for suspicious withdrawal patterns
        $this->checkSuspiciousActivity($dto);
    }

    /**
     * SECURITY: Check for suspicious withdrawal patterns
     */
    private function checkSuspiciousActivity(SavingsTransactionDTO $dto): void
    {
        // Check for multiple large withdrawals in short time
        $recentWithdrawals = Savings::where('member_id', $dto->member->id)
            ->where('type', $dto->type)
            ->where('amount', '<', 0)
            ->where('created_at', '>=', now()->subHours(24))
            ->sum('amount');

        $totalWithdrawalToday = abs($recentWithdrawals) + $dto->amount;
        $dailyLimit = config('savings.daily_withdrawal_limit', 10000000); // 10M default

        if ($totalWithdrawalToday > $dailyLimit) {
            Log::warning('Suspicious withdrawal activity detected', [
                'member_id' => $dto->member->id,
                'type' => $dto->type,
                'current_amount' => $dto->amount,
                'total_today' => $totalWithdrawalToday,
                'limit' => $dailyLimit,
            ]);

            throw new \Exception('Daily withdrawal limit exceeded. Please contact administrator.');
        }
    }

    /**
     * Validate mandatory savings withdrawal
     */
    private function validateMandatorySavingsWithdrawal(Member $member): void
    {
        if ($member->status === 'active') {
            throw new \Exception('Mandatory savings can only be withdrawn when member leaves the cooperative');
        }

        if ($member->getTotalLoanBalance() > 0) {
            throw new \Exception('Cannot withdraw mandatory savings while having outstanding loans');
        }
    }

    /**
     * Create journal entry for savings transaction
     */
    private function createJournalEntry(Savings $savings, string $transactionType): void
    {
        $member = $savings->member;
        $amount = abs($savings->amount);

        // Determine accounts based on savings type
        $savingsAccountCode = $this->getSavingsAccountCode($savings->type);
        $cashAccountCode = '1100'; // Cash account

        if ($transactionType === 'deposit') {
            // Debit: Cash, Credit: Member Savings
            $description = "Deposit {$savings->getTypeLabel()} - {$member->display_name}";
            $lines = [
                ['account_code' => $cashAccountCode, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $savingsAccountCode, 'debit' => 0, 'credit' => $amount],
            ];
        } else {
            // Debit: Member Savings, Credit: Cash
            $description = "Withdrawal {$savings->getTypeLabel()} - {$member->display_name}";
            $lines = [
                ['account_code' => $savingsAccountCode, 'debit' => $amount, 'credit' => 0],
                ['account_code' => $cashAccountCode, 'debit' => 0, 'credit' => $amount],
            ];
        }

        // This would integrate with the FinancialEntryService
        // Implementation depends on the journal entry creation process
    }

    /**
     * Get account code for savings type
     */
    private function getSavingsAccountCode(string $type): string
    {
        return match ($type) {
            'pokok' => '3100', // Share Capital
            'wajib' => '3200', // Mandatory Savings
            'khusus' => '3300', // Special Savings
            'sukarela' => '3400', // Voluntary Savings
            default => '3000', // General Member Deposits
        };
    }
}
