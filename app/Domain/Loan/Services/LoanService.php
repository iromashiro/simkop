<?php

namespace App\Domain\Loan\Services;

use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Loan\Models\LoanPayment;
use App\Domain\Loan\DTOs\CreateLoanAccountDTO;
use App\Domain\Loan\DTOs\LoanPaymentDTO;
use App\Domain\Loan\Contracts\LoanRepositoryInterface;
use App\Domain\Loan\Exceptions\LoanNotFoundException;
use App\Domain\Loan\Exceptions\LoanValidationException;
use App\Domain\Loan\Events\LoanAccountCreated;
use App\Domain\Loan\Events\LoanPaymentProcessed;
use App\Domain\Loan\Events\LoanPaidOff;
use App\Domain\Member\Services\MemberService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Loan Service - Complete Implementation with Events
 *
 * Handles all loan-related business logic including:
 * - Loan account creation and management
 * - Payment processing and tracking
 * - Interest calculations
 * - Event dispatching for business processes
 * - Multi-tenant security validation
 *
 * @package App\Domain\Loan\Services
 * @author Mateen (Senior Software Engineer)
 */
class LoanService
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private MemberService $memberService
    ) {}

    /**
     * Create new loan account with complete validation
     *
     * @param CreateLoanAccountDTO $dto
     * @return LoanAccount
     * @throws LoanValidationException
     * @throws \Exception
     */
    public function createLoanAccount(CreateLoanAccountDTO $dto): LoanAccount
    {
        return DB::transaction(function () use ($dto) {
            // Validate member exists and is eligible
            $member = $this->memberService->findById($dto->member_id);
            if (!$member || $member->status !== 'active') {
                throw LoanValidationException::memberNotEligible($dto->member_id);
            }

            // Validate loan parameters
            $this->validateLoanParameters($dto);

            // Check member loan eligibility
            $this->validateMemberEligibility($member, $dto);

            // Calculate monthly payment
            $monthlyPayment = $this->calculateMonthlyPayment($dto);

            // Generate loan number
            $loanNumber = $this->generateLoanNumber($member->cooperative_id);

            // Create loan account
            $loanAccount = $this->loanRepository->create([
                'member_id' => $dto->member_id,
                'cooperative_id' => $member->cooperative_id,
                'loan_number' => $loanNumber,
                'loan_type' => $dto->loan_type,
                'principal_amount' => $dto->principal_amount,
                'interest_rate' => $dto->interest_rate,
                'term_months' => $dto->term_months,
                'monthly_payment' => $monthlyPayment,
                'outstanding_balance' => $dto->principal_amount,
                'status' => 'active',
                'disbursement_date' => $dto->disbursement_date->format('Y-m-d'),
                'maturity_date' => $dto->disbursement_date->addMonths($dto->term_months)->format('Y-m-d'),
                'created_by' => auth()->id(),
            ]);

            // Clear cache
            $this->clearLoanCache($member->cooperative_id);

            // Dispatch loan created event
            event(new LoanAccountCreated($loanAccount, auth()->id()));

            Log::info('Loan account created', [
                'loan_id' => $loanAccount->id,
                'loan_number' => $loanAccount->loan_number,
                'member_id' => $dto->member_id,
                'amount' => $dto->principal_amount,
                'created_by' => auth()->id()
            ]);

            return $loanAccount;
        });
    }

    /**
     * Process loan payment with complete validation
     *
     * @param LoanPaymentDTO $dto
     * @return LoanPayment
     * @throws LoanNotFoundException
     * @throws LoanValidationException
     */
    public function processPayment(LoanPaymentDTO $dto): LoanPayment
    {
        return DB::transaction(function () use ($dto) {
            $loanAccount = $this->findById($dto->loan_account_id);
            if (!$loanAccount) {
                throw LoanNotFoundException::forId($dto->loan_account_id);
            }

            // Validate payment
            $this->validatePayment($loanAccount, $dto);

            // Create payment record
            $payment = $loanAccount->payments()->create([
                'payment_date' => $dto->payment_date->format('Y-m-d'),
                'amount' => $dto->amount,
                'principal_amount' => $dto->principal_amount,
                'interest_amount' => $dto->interest_amount,
                'payment_method' => $dto->payment_method,
                'reference_number' => $this->generatePaymentReference(),
                'created_by' => auth()->id(),
            ]);

            // Update loan balance
            $newBalance = $loanAccount->outstanding_balance - $dto->principal_amount;
            $newStatus = $newBalance <= 0 ? 'paid_off' : 'active';

            $loanAccount->update([
                'outstanding_balance' => max(0, $newBalance),
                'status' => $newStatus,
                'paid_off_date' => $newStatus === 'paid_off' ? now() : null,
            ]);

            // Clear cache
            $this->clearLoanCache($loanAccount->cooperative_id);

            // Dispatch payment processed event
            event(new LoanPaymentProcessed($payment, auth()->id()));

            // Dispatch loan paid off event if fully paid
            if ($newStatus === 'paid_off') {
                event(new LoanPaidOff($loanAccount, auth()->id()));
            }

            Log::info('Loan payment processed', [
                'payment_id' => $payment->id,
                'loan_id' => $loanAccount->id,
                'amount' => $dto->amount,
                'new_balance' => $newBalance,
                'status' => $newStatus,
                'processed_by' => auth()->id()
            ]);

            return $payment;
        });
    }

    /**
     * Find loan account by ID
     *
     * @param int $id
     * @return LoanAccount|null
     */
    public function findById(int $id): ?LoanAccount
    {
        return $this->loanRepository->findById($id);
    }

    /**
     * Find loan account by loan number
     *
     * @param string $loanNumber
     * @param int $cooperativeId
     * @return LoanAccount|null
     */
    public function findByLoanNumber(string $loanNumber, int $cooperativeId): ?LoanAccount
    {
        return $this->loanRepository->findByLoanNumber($loanNumber, $cooperativeId);
    }

    /**
     * Get loans by member
     *
     * @param int $memberId
     * @return Collection
     */
    public function getLoansByMember(int $memberId): Collection
    {
        return $this->loanRepository->getByMember($memberId);
    }

    /**
     * Get paginated loans for cooperative
     *
     * @param int $cooperativeId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedLoans(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->loanRepository->getPaginated($cooperativeId, $filters, $perPage);
    }

    /**
     * Get outstanding loans for cooperative
     *
     * @param int $cooperativeId
     * @return Collection
     */
    public function getOutstandingLoans(int $cooperativeId): Collection
    {
        return Cache::tags(['loans', "cooperative_{$cooperativeId}"])
            ->remember("outstanding_loans_{$cooperativeId}", 300, function () use ($cooperativeId) {
                return $this->loanRepository->getOutstandingLoans($cooperativeId);
            });
    }

    /**
     * Get loan statistics
     *
     * @param int $cooperativeId
     * @return array
     */
    public function getLoanStatistics(int $cooperativeId): array
    {
        return Cache::tags(['loans', "cooperative_{$cooperativeId}"])
            ->remember("loan_stats_{$cooperativeId}", 300, function () use ($cooperativeId) {
                return [
                    'total_outstanding' => $this->loanRepository->getTotalOutstanding($cooperativeId),
                    'total_loans' => $this->loanRepository->getByCooperative($cooperativeId)->count(),
                    'active_loans' => $this->loanRepository->getByCooperative($cooperativeId)->where('status', 'active')->count(),
                    'paid_off_loans' => $this->loanRepository->getByCooperative($cooperativeId)->where('status', 'paid_off')->count(),
                ];
            });
    }

    /**
     * Calculate loan payment schedule
     *
     * @param LoanAccount $loanAccount
     * @return array
     */
    public function calculatePaymentSchedule(LoanAccount $loanAccount): array
    {
        $schedule = [];
        $balance = $loanAccount->principal_amount;
        $monthlyRate = $loanAccount->interest_rate / 100 / 12;
        $monthlyPayment = $loanAccount->monthly_payment;

        for ($month = 1; $month <= $loanAccount->term_months; $month++) {
            $interestPayment = $balance * $monthlyRate;
            $principalPayment = $monthlyPayment - $interestPayment;
            $balance -= $principalPayment;

            $schedule[] = [
                'month' => $month,
                'payment_date' => $loanAccount->disbursement_date->addMonths($month)->format('Y-m-d'),
                'payment_amount' => round($monthlyPayment, 2),
                'principal_amount' => round($principalPayment, 2),
                'interest_amount' => round($interestPayment, 2),
                'remaining_balance' => round(max(0, $balance), 2),
            ];
        }

        return $schedule;
    }

    /**
     * Calculate early payoff amount
     *
     * @param LoanAccount $loanAccount
     * @param string $payoffDate
     * @return array
     */
    public function calculateEarlyPayoff(LoanAccount $loanAccount, string $payoffDate): array
    {
        $payoffDate = \Carbon\Carbon::parse($payoffDate);
        $monthsRemaining = $payoffDate->diffInMonths($loanAccount->maturity_date);

        // Simple calculation - in real implementation, consider prepayment penalties
        $earlyPayoffAmount = $loanAccount->outstanding_balance;
        $interestSavings = $monthsRemaining * ($loanAccount->monthly_payment - ($loanAccount->outstanding_balance / $monthsRemaining));

        return [
            'payoff_amount' => round($earlyPayoffAmount, 2),
            'interest_savings' => round(max(0, $interestSavings), 2),
            'payoff_date' => $payoffDate->format('Y-m-d'),
            'months_saved' => $monthsRemaining,
        ];
    }

    /**
     * Validate loan parameters
     *
     * @param CreateLoanAccountDTO $dto
     * @throws LoanValidationException
     */
    private function validateLoanParameters(CreateLoanAccountDTO $dto): void
    {
        if ($dto->principal_amount <= 0) {
            throw LoanValidationException::invalidAmount($dto->principal_amount);
        }

        if ($dto->interest_rate < 0 || $dto->interest_rate > 100) {
            throw LoanValidationException::invalidInterestRate($dto->interest_rate);
        }

        if ($dto->term_months <= 0 || $dto->term_months > 360) {
            throw LoanValidationException::invalidTerm($dto->term_months);
        }

        if ($dto->disbursement_date->isPast()) {
            throw LoanValidationException::invalidDisbursementDate($dto->disbursement_date);
        }
    }

    /**
     * Validate member eligibility for loan
     *
     * @param \App\Domain\Member\Models\Member $member
     * @param CreateLoanAccountDTO $dto
     * @throws LoanValidationException
     */
    private function validateMemberEligibility($member, CreateLoanAccountDTO $dto): void
    {
        // Check member age
        if ($member->age < 21) {
            throw LoanValidationException::memberNotEligible($member->id, 'Member must be at least 21 years old');
        }

        // Check existing loans
        $existingLoans = $this->loanRepository->getByMember($member->id)
            ->where('status', 'active');

        if ($existingLoans->count() >= 3) {
            throw LoanValidationException::memberNotEligible($member->id, 'Member has too many active loans');
        }

        // Check total outstanding amount
        $totalOutstanding = $existingLoans->sum('outstanding_balance');
        $maxLoanAmount = $member->monthly_income * 5; // 5x monthly income

        if (($totalOutstanding + $dto->principal_amount) > $maxLoanAmount) {
            throw LoanValidationException::memberNotEligible($member->id, 'Loan amount exceeds maximum allowed');
        }
    }

    /**
     * Validate payment
     *
     * @param LoanAccount $loanAccount
     * @param LoanPaymentDTO $dto
     * @throws LoanValidationException
     */
    private function validatePayment(LoanAccount $loanAccount, LoanPaymentDTO $dto): void
    {
        if ($loanAccount->status !== 'active') {
            throw LoanValidationException::loanNotActive($loanAccount->id);
        }

        if ($dto->principal_amount > $loanAccount->outstanding_balance) {
            throw LoanValidationException::excessivePayment($dto->principal_amount, $loanAccount->outstanding_balance);
        }

        if ($dto->amount <= 0) {
            throw LoanValidationException::invalidPaymentAmount($dto->amount);
        }
    }

    /**
     * Calculate monthly payment using amortization formula
     *
     * @param CreateLoanAccountDTO $dto
     * @return float
     */
    private function calculateMonthlyPayment(CreateLoanAccountDTO $dto): float
    {
        $monthlyRate = $dto->interest_rate / 100 / 12;
        $numPayments = $dto->term_months;

        if ($monthlyRate == 0) {
            return $dto->principal_amount / $numPayments;
        }

        $monthlyPayment = $dto->principal_amount *
            ($monthlyRate * pow(1 + $monthlyRate, $numPayments)) /
            (pow(1 + $monthlyRate, $numPayments) - 1);

        return round($monthlyPayment, 2);
    }

    /**
     * Generate unique loan number
     *
     * @param int $cooperativeId
     * @return string
     */
    private function generateLoanNumber(int $cooperativeId): string
    {
        $lastLoan = $this->loanRepository->getLastLoan($cooperativeId);
        $nextNumber = $lastLoan ? (int)substr($lastLoan->loan_number, -6) + 1 : 1;

        return 'LN' . date('Ym') . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Generate payment reference number
     *
     * @return string
     */
    private function generatePaymentReference(): string
    {
        return 'PAY' . date('YmdHis') . rand(100, 999);
    }

    /**
     * Clear loan cache for cooperative
     *
     * @param int $cooperativeId
     * @return void
     */
    private function clearLoanCache(int $cooperativeId): void
    {
        Cache::tags(["cooperative_{$cooperativeId}", 'loans'])->flush();
    }
}
