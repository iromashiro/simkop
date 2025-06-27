<?php
// CreateLoanAccountDTO.php
namespace App\Domain\Loan\DTOs;

use Carbon\Carbon;

class CreateLoanAccountDTO
{
    public function __construct(
        public readonly int $member_id,
        public readonly string $loan_type,
        public readonly float $principal_amount,
        public readonly float $interest_rate,
        public readonly int $term_months,
        public readonly Carbon $disbursement_date,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            member_id: $data['member_id'],
            loan_type: $data['loan_type'],
            principal_amount: (float)$data['principal_amount'],
            interest_rate: (float)$data['interest_rate'],
            term_months: (int)$data['term_months'],
            disbursement_date: Carbon::parse($data['disbursement_date']),
        );
    }

    private function validate(): void
    {
        if ($this->principal_amount <= 0) {
            throw new \InvalidArgumentException('Principal amount must be positive');
        }

        if ($this->interest_rate < 0 || $this->interest_rate > 100) {
            throw new \InvalidArgumentException('Interest rate must be between 0 and 100');
        }

        if ($this->term_months <= 0) {
            throw new \InvalidArgumentException('Term must be positive');
        }
    }
}

// LoanPaymentDTO.php
namespace App\Domain\Loan\DTOs;

use Carbon\Carbon;

class LoanPaymentDTO
{
    public function __construct(
        public readonly int $loan_account_id,
        public readonly Carbon $payment_date,
        public readonly float $amount,
        public readonly float $principal_amount,
        public readonly float $interest_amount,
        public readonly string $payment_method,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            loan_account_id: $data['loan_account_id'],
            payment_date: Carbon::parse($data['payment_date']),
            amount: (float)$data['amount'],
            principal_amount: (float)$data['principal_amount'],
            interest_amount: (float)$data['interest_amount'],
            payment_method: $data['payment_method'],
        );
    }

    private function validate(): void
    {
        if ($this->amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be positive');
        }

        if (abs($this->amount - ($this->principal_amount + $this->interest_amount)) > 0.01) {
            throw new \InvalidArgumentException('Payment amount must equal principal + interest');
        }
    }
}
