<?php
// FiscalPeriodRepositoryInterface.php
namespace App\Domain\Accounting\Contracts;

use App\Domain\Accounting\Models\FiscalPeriod;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

interface FiscalPeriodRepositoryInterface
{
    public function create(array $data): FiscalPeriod;
    public function findById(int $id): ?FiscalPeriod;
    public function update(int $id, array $data): FiscalPeriod;
    public function getActive(int $cooperativeId): ?FiscalPeriod;
    public function getByCooperative(int $cooperativeId): Collection;
    public function findOverlapping(int $cooperativeId, Carbon $startDate, Carbon $endDate): Collection;
}

// JournalEntryRepositoryInterface.php
namespace App\Domain\Accounting\Contracts;

use App\Domain\Accounting\Models\JournalEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface JournalEntryRepositoryInterface
{
    public function create(array $data): JournalEntry;
    public function findById(int $id): ?JournalEntry;
    public function update(int $id, array $data): JournalEntry;
    public function getLastEntry(int $cooperativeId): ?JournalEntry;
    public function getByPeriod(int $fiscalPeriodId): Collection;
    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getAccountBalance(int $accountId, string $asOfDate): float;
}

// LoanRepositoryInterface.php
namespace App\Domain\Loan\Contracts;

use App\Domain\Loan\Models\LoanAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface LoanRepositoryInterface
{
    public function create(array $data): LoanAccount;
    public function findById(int $id): ?LoanAccount;
    public function update(int $id, array $data): LoanAccount;
    public function getLastLoan(int $cooperativeId): ?LoanAccount;
    public function getByMember(int $memberId): Collection;
    public function getByCooperative(int $cooperativeId): Collection;
    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getOutstandingLoans(int $cooperativeId): Collection;
    public function getTotalOutstanding(int $cooperativeId): float;
}

// SavingsRepositoryInterface.php
namespace App\Domain\Savings\Contracts;

use App\Domain\Savings\Models\SavingsAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface SavingsRepositoryInterface
{
    public function create(array $data): SavingsAccount;
    public function findById(int $id): ?SavingsAccount;
    public function update(int $id, array $data): SavingsAccount;
    public function getLastAccount(int $cooperativeId): ?SavingsAccount;
    public function getByMember(int $memberId): Collection;
    public function getByCooperative(int $cooperativeId): Collection;
    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getTotalBalance(int $cooperativeId): float;
    public function getAccountStatistics(int $cooperativeId): array;
}
