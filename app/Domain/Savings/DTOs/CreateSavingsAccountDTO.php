<?php
// CreateSavingsAccountDTO.php
namespace App\Domain\Savings\DTOs;

class CreateSavingsAccountDTO
{
    public function __construct(
        public readonly int $member_id,
        public readonly string $account_type,
        public readonly float $interest_rate,
        public readonly float $minimum_balance,
        public readonly float $initial_deposit = 0,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            member_id: $data['member_id'],
            account_type: $data['account_type'],
            interest_rate: (float)$data['interest_rate'],
            minimum_balance: (float)$data['minimum_balance'],
            initial_deposit: (float)($data['initial_deposit'] ?? 0),
        );
    }

    private function validate(): void
    {
        if ($this->interest_rate < 0 || $this->interest_rate > 100) {
            throw new \InvalidArgumentException('Interest rate must be between 0 and 100');
        }

        if ($this->minimum_balance < 0) {
            throw new \InvalidArgumentException('Minimum balance cannot be negative');
        }

        if ($this->initial_deposit < 0) {
            throw new \InvalidArgumentException('Initial deposit cannot be negative');
        }
    }
}

// SavingsTransactionDTO.php
namespace App\Domain\Savings\DTOs;

use Carbon\Carbon;

class SavingsTransactionDTO
{
    public function __construct(
        public readonly int $savings_account_id,
        public readonly string $transaction_type,
        public readonly float $amount,
        public readonly Carbon $transaction_date,
        public readonly string $description,
        public readonly ?string $reference_number = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            savings_account_id: $data['savings_account_id'],
            transaction_type: $data['transaction_type'],
            amount: (float)$data['amount'],
            transaction_date: Carbon::parse($data['transaction_date']),
            description: trim($data['description']),
            reference_number: $data['reference_number'] ?? null,
        );
    }

    private function validate(): void
    {
        if (!in_array($this->transaction_type, ['deposit', 'withdrawal'])) {
            throw new \InvalidArgumentException('Invalid transaction type');
        }

        if ($this->amount <= 0) {
            throw new \InvalidArgumentException('Amount must be positive');
        }
    }
}
