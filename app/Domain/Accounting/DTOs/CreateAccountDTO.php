<?php
// CreateAccountDTO.php
namespace App\Domain\Accounting\DTOs;

class CreateAccountDTO
{
    public function __construct(
        public readonly int $cooperative_id,
        public readonly string $code,
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $parent_id = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cooperative_id: $data['cooperative_id'],
            code: strtoupper(trim($data['code'])),
            name: trim($data['name']),
            type: $data['type'],
            parent_id: $data['parent_id'] ?? null,
        );
    }

    private function validate(): void
    {
        if (!in_array($this->type, ['asset', 'liability', 'equity', 'revenue', 'expense'])) {
            throw new \InvalidArgumentException('Invalid account type');
        }
    }
}

// UpdateAccountDTO.php
namespace App\Domain\Accounting\DTOs;

class UpdateAccountDTO
{
    public function __construct(
        public readonly ?string $code = null,
        public readonly ?string $name = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            code: !empty($data['code']) ? strtoupper(trim($data['code'])) : null,
            name: !empty($data['name']) ? trim($data['name']) : null,
        );
    }
}

// CreateFiscalPeriodDTO.php
namespace App\Domain\Accounting\DTOs;

use Carbon\Carbon;

class CreateFiscalPeriodDTO
{
    public function __construct(
        public readonly int $cooperative_id,
        public readonly string $name,
        public readonly Carbon $start_date,
        public readonly Carbon $end_date,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            cooperative_id: $data['cooperative_id'],
            name: trim($data['name']),
            start_date: Carbon::parse($data['start_date']),
            end_date: Carbon::parse($data['end_date']),
        );
    }

    private function validate(): void
    {
        if ($this->start_date->isAfter($this->end_date)) {
            throw new \InvalidArgumentException('Start date must be before end date');
        }
    }
}

// CreateJournalEntryDTO.php
namespace App\Domain\Accounting\DTOs;

use Carbon\Carbon;

class CreateJournalEntryDTO
{
    public function __construct(
        public readonly int $cooperative_id,
        public readonly int $fiscal_period_id,
        public readonly Carbon $transaction_date,
        public readonly string $description,
        public readonly array $lines,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        $lines = array_map(fn($line) => JournalEntryLineDTO::fromArray($line), $data['lines']);

        return new self(
            cooperative_id: $data['cooperative_id'],
            fiscal_period_id: $data['fiscal_period_id'],
            transaction_date: Carbon::parse($data['transaction_date']),
            description: trim($data['description']),
            lines: $lines,
        );
    }

    public function getTotalDebit(): float
    {
        return array_sum(array_map(fn($line) => $line->debit_amount, $this->lines));
    }

    public function getTotalCredit(): float
    {
        return array_sum(array_map(fn($line) => $line->credit_amount, $this->lines));
    }

    private function validate(): void
    {
        if (count($this->lines) < 2) {
            throw new \InvalidArgumentException('Journal entry must have at least 2 lines');
        }
    }
}

// JournalEntryLineDTO.php
namespace App\Domain\Accounting\DTOs;

class JournalEntryLineDTO
{
    public function __construct(
        public readonly int $account_id,
        public readonly string $description,
        public readonly float $debit_amount,
        public readonly float $credit_amount,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            account_id: $data['account_id'],
            description: trim($data['description']),
            debit_amount: (float)($data['debit_amount'] ?? 0),
            credit_amount: (float)($data['credit_amount'] ?? 0),
        );
    }

    private function validate(): void
    {
        if ($this->debit_amount > 0 && $this->credit_amount > 0) {
            throw new \InvalidArgumentException('Line cannot have both debit and credit amounts');
        }

        if ($this->debit_amount === 0 && $this->credit_amount === 0) {
            throw new \InvalidArgumentException('Line must have either debit or credit amount');
        }
    }
}
