<?php

namespace App\Domain\Savings\Services;

use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Domain\Savings\DTOs\CreateSavingsAccountDTO;
use App\Domain\Savings\DTOs\SavingsTransactionDTO;
use App\Domain\Savings\Contracts\SavingsRepositoryInterface;
use App\Domain\Savings\Exceptions\SavingsNotFoundException;
use App\Domain\Savings\Exceptions\SavingsValidationException;
use App\Domain\Savings\Events\SavingsAccountCreated;
use App\Domain\Savings\Events\SavingsTransactionProcessed;
use App\Domain\Member\Services\MemberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Savings Service - Complete Implementation with Events
 *
 * Handles all savings-related business logic including:
 * - Savings account creation and management
 * - Transaction processing (deposits/withdrawals)
 * - Interest calculations
 * - Event dispatching for business processes
 * - Multi-tenant security validation
 *
 * @package App\Domain\Savings\Services
 * @author Mateen (Senior Software Engineer)
 */
class SavingsService
{
    public function __construct(
        private SavingsRepositoryInterface $savingsRepository,
        private MemberService $memberService
    ) {}

    /**
     * Create new savings account with complete validation
     *
     * @param CreateSavingsAccountDTO $dto
     * @return SavingsAccount
     * @throws SavingsValidationException
     * @throws \Exception
     */
    public function createSavingsAccount(CreateSavingsAccountDTO $dto): SavingsAccount
    {
        return DB::transaction(function () use ($dto) {
            // Validate member exists and is active
            $member = $this->memberService->findById($dto->member_id);
            if (!$member || $member->status !== 'active') {
                throw SavingsValidationException::memberNotEligible($dto->member_id);
            }

            // Validate savings parameters
            $this->validateSavingsParameters($dto);

            // Generate account number
            $accountNumber = $this->generateAccountNumber($member->cooperative_id);

            // Create savings account
            $savingsAccount = $this->savingsRepository->create([
                'member_id' => $dto->member_id,
                'cooperative_id' => $member->cooperative_id,
                'account_number' => $accountNumber,
                'account_type' => $dto->account_type,
                'balance' => 0,
                'interest_rate' => $dto->interest_rate,
                'minimum_balance' => $dto->minimum_balance,
                'status' => 'active',
                'opened_date' => now()->format('Y-m-d'),
                'created_by' => auth()->id(),
            ]);

            // Process initial deposit if provided
            if ($dto->initial_deposit > 0) {
                $this->processTransaction(new SavingsTransactionDTO(
                    savings_account_id: $savingsAccount->id,
                    transaction_type: 'deposit',
                    amount: $dto->initial_deposit,
                    transaction_date: now(),
                    description: 'Initial deposit',
                    reference_number: null
                ));
            }

            // Clear cache
            $this->clearSavingsCache($member->cooperative_id);

            // Dispatch savings account created event
            event(new SavingsAccountCreated($savingsAccount, auth()->id()));

            Log::info('Savings account created', [
                'savings_id' => $savingsAccount->id,
                'account_number' => $savingsAccount->account_number,
                'member_id' => $dto->member_id,
                'initial_deposit' => $dto->initial_deposit,
                'created_by' => auth()->id()
            ]);

            return $savingsAccount;
        });
    }

    /**
     * Process savings transaction (deposit/withdrawal)
     *
     * @param SavingsTransactionDTO $dto
     * @return SavingsTransaction
     * @throws SavingsNotFoundException
     * @throws SavingsValidationException
     */
    public function processTransaction(SavingsTransactionDTO $dto): SavingsTransaction
    {
        return DB::transaction(function () use ($dto) {
            $savingsAccount = $this->findById($dto->savings_account_id);
            if (!$savingsAccount) {
                throw SavingsNotFoundException::forId($dto->savings_account_id);
            }

            // Validate transaction
            $this->validateTransaction($savingsAccount, $dto);

            // Calculate new balance
            $balanceChange = $dto->transaction_type === 'deposit' ? $dto->amount : -$dto->amount;
            $newBalance = $savingsAccount->balance + $balanceChange;

            // Validate minimum balance for withdrawals
            if ($dto->transaction_type === 'withdrawal' && $newBalance < $savingsAccount->minimum_balance) {
                throw SavingsValidationException::belowMinimumBalance($newBalance, $savingsAccount->minimum_balance);
            }

            // Create transaction record
            $transaction = $savingsAccount->transactions()->create([
                'transaction_type' => $dto->transaction_type,
                'amount' => $dto->amount,
                'transaction_date' => $dto->transaction_date->format('Y-m-d'),
                'description' => $dto->description,
                'reference_number' => $dto->reference_number ?: $this->generateTransactionReference(),
                'balance_before' => $savingsAccount->balance,
                'balance_after' => $newBalance,
                'created_by' => auth()->id(),
            ]);

            // Update account balance
            $savingsAccount->update([
                'balance' => $newBalance,
                'last_transaction_date' => $dto->transaction_date->format('Y-m-d'),
            ]);

            // Clear cache
            $this->clearSavingsCache($savingsAccount->cooperative_id);

            // Dispatch transaction processed event
            event(new SavingsTransactionProcessed($transaction, auth()->id()));

            Log::info('Savings transaction processed', [
                'transaction_id' => $transaction->id,
                'savings_id' => $savingsAccount->id,
                'type' => $dto->transaction_type,
                'amount' => $dto->amount,
                'new_balance' => $newBalance,
                'processed_by' => auth()->id()
            ]);

            return $transaction;
        });
    }

    /**
     * Find savings account by ID
     *
     * @param int $id
     * @return SavingsAccount|null
     */
    public function findById(int $id): ?SavingsAccount
    {
        return $this->savingsRepository->findById($id);
    }

    /**
     * Find savings account by account number
     *
     * @param string $accountNumber
     * @param int $cooperativeId
     * @return SavingsAccount|null
     */
    public function findByAccountNumber(string $accountNumber, int $cooperativeId): ?SavingsAccount
    {
        return $this->savingsRepository->findByAccountNumber($accountNumber, $cooperativeId);
    }

    /**
     * Get savings accounts by member
     *
     * @param int $memberId
     * @return Collection
     */
    public function getSavingsByMember(int $memberId): Collection
    {
        return $this->savingsRepository->getByMember($memberId);
    }

    /**
     * Get paginated savings accounts for cooperative
     *
     * @param int $cooperativeId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedSavings(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->savingsRepository->getPaginated($cooperativeId, $filters, $perPage);
    }

    /**
     * Get savings statistics
     *
     * @param int $cooperativeId
     * @return array
     */
    public function getSavingsStatistics(int $cooperativeId): array
    {
        return Cache::tags(['savings', "cooperative_{$cooperativeId}"])
            ->remember("savings_stats_{$cooperativeId}", 300, function () use ($cooperativeId) {
                return $this->savingsRepository->getAccountStatistics($cooperativeId);
            });
    }

    /**
     * Calculate interest for savings account
     *
     * @param SavingsAccount $savingsAccount
     * @param string $asOfDate
     * @return float
     */
    public function calculateInterest(SavingsAccount $savingsAccount, string $asOfDate): float
    {
        $asOfDate = \Carbon\Carbon::parse($asOfDate);
        $lastInterestDate = $savingsAccount->last_interest_date ?
            \Carbon\Carbon::parse($savingsAccount->last_interest_date) :
            \Carbon\Carbon::parse($savingsAccount->opened_date);

        $daysDiff = $lastInterestDate->diffInDays($asOfDate);
        $dailyRate = $savingsAccount->interest_rate / 100 / 365;

        return round($savingsAccount->balance * $dailyRate * $daysDiff, 2);
    }

    /**
     * Process interest payment for savings account
     *
     * @param int $savingsAccountId
     * @param string $asOfDate
     * @return SavingsTransaction|null
     */
    public function processInterestPayment(int $savingsAccountId, string $asOfDate): ?SavingsTransaction
    {
        return DB::transaction(function () use ($savingsAccountId, $asOfDate) {
            $savingsAccount = $this->findById($savingsAccountId);
            if (!$savingsAccount) {
                throw SavingsNotFoundException::forId($savingsAccountId);
            }

            $interestAmount = $this->calculateInterest($savingsAccount, $asOfDate);

            if ($interestAmount <= 0) {
                return null; // No interest to pay
            }

            // Create interest transaction
            $transaction = $this->processTransaction(new SavingsTransactionDTO(
                savings_account_id: $savingsAccountId,
                transaction_type: 'deposit',
                amount: $interestAmount,
                transaction_date: \Carbon\Carbon::parse($asOfDate),
                description: 'Interest payment',
                reference_number: 'INT' . date('YmdHis')
            ));

            // Update last interest date
            $savingsAccount->update([
                'last_interest_date' => $asOfDate,
            ]);

            Log::info('Interest payment processed', [
                'savings_id' => $savingsAccountId,
                'interest_amount' => $interestAmount,
                'as_of_date' => $asOfDate
            ]);

            return $transaction;
        });
    }

    /**
     * Bulk process interest for all eligible accounts
     *
     * @param int $cooperativeId
     * @param string $asOfDate
     * @return array
     */
    public function bulkProcessInterest(int $cooperativeId, string $asOfDate): array
    {
        $accounts = $this->savingsRepository->getByCooperative($cooperativeId)
            ->where('status', 'active')
            ->where('balance', '>', 0);

        $processed = 0;
        $totalInterest = 0;
        $errors = [];

        foreach ($accounts as $account) {
            try {
                $transaction = $this->processInterestPayment($account->id, $asOfDate);
                if ($transaction) {
                    $processed++;
                    $totalInterest += $transaction->amount;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'account_id' => $account->id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'total_accounts' => $accounts->count(),
            'processed_accounts' => $processed,
            'total_interest_paid' => $totalInterest,
            'errors' => $errors,
        ];
    }

    /**
     * Close savings account
     *
     * @param int $id
     * @param string $reason
     * @return SavingsAccount
     * @throws SavingsNotFoundException
     * @throws SavingsValidationException
     */
    public function closeSavingsAccount(int $id, string $reason = ''): SavingsAccount
    {
        return DB::transaction(function () use ($id, $reason) {
            $savingsAccount = $this->findById($id);
            if (!$savingsAccount) {
                throw SavingsNotFoundException::forId($id);
            }

            if ($savingsAccount->balance > 0) {
                throw SavingsValidationException::accountHasBalance($id, $savingsAccount->balance);
            }

            $savingsAccount->update([
                'status' => 'closed',
                'closed_date' => now(),
                'closure_reason' => $reason,
                'closed_by' => auth()->id(),
            ]);

            $this->clearSavingsCache($savingsAccount->cooperative_id);

            Log::info('Savings account closed', [
                'savings_id' => $id,
                'reason' => $reason,
                'closed_by' => auth()->id()
            ]);

            return $savingsAccount;
        });
    }

    /**
     * Validate savings parameters
     *
     * @param CreateSavingsAccountDTO $dto
     * @throws SavingsValidationException
     */
    private function validateSavingsParameters(CreateSavingsAccountDTO $dto): void
    {
        if ($dto->interest_rate < 0 || $dto->interest_rate > 100) {
            throw SavingsValidationException::invalidInterestRate($dto->interest_rate);
        }

        if ($dto->minimum_balance < 0) {
            throw SavingsValidationException::invalidMinimumBalance($dto->minimum_balance);
        }

        if ($dto->initial_deposit < 0) {
            throw SavingsValidationException::invalidInitialDeposit($dto->initial_deposit);
        }

        if ($dto->initial_deposit > 0 && $dto->initial_deposit < $dto->minimum_balance) {
            throw SavingsValidationException::initialDepositBelowMinimum($dto->initial_deposit, $dto->minimum_balance);
        }
    }

    /**
     * Validate transaction
     *
     * @param SavingsAccount $savingsAccount
     * @param SavingsTransactionDTO $dto
     * @throws SavingsValidationException
     */
    private function validateTransaction(SavingsAccount $savingsAccount, SavingsTransactionDTO $dto): void
    {
        if ($savingsAccount->status !== 'active') {
            throw SavingsValidationException::accountInactive($savingsAccount->id);
        }

        if (!in_array($dto->transaction_type, ['deposit', 'withdrawal'])) {
            throw SavingsValidationException::invalidTransactionType($dto->transaction_type);
        }

        if ($dto->amount <= 0) {
            throw SavingsValidationException::invalidTransactionAmount($dto->amount);
        }

        if ($dto->transaction_type === 'withdrawal' && $dto->amount > $savingsAccount->balance) {
            throw SavingsValidationException::insufficientBalance($savingsAccount->balance, $dto->amount);
        }
    }

    /**
     * Generate unique account number
     *
     * @param int $cooperativeId
     * @return string
     */
    private function generateAccountNumber(int $cooperativeId): string
    {
        $lastAccount = $this->savingsRepository->getLastAccount($cooperativeId);
        $nextNumber = $lastAccount ? (int)substr($lastAccount->account_number, -8) + 1 : 1;

        return 'SAV' . str_pad($nextNumber, 8, '0', STR_PAD_LEFT);
    }

    /**
     * Generate transaction reference number
     *
     * @return string
     */
    private function generateTransactionReference(): string
    {
        return 'TXN' . date('YmdHis') . rand(100, 999);
    }

    /**
     * Clear savings cache for cooperative
     *
     * @param int $cooperativeId
     * @return void
     */
    private function clearSavingsCache(int $cooperativeId): void
    {
        Cache::tags(["cooperative_{$cooperativeId}", 'savings'])->flush();
    }
}
