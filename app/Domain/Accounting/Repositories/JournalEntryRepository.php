<?php

namespace App\Domain\Accounting\Repositories;

use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Contracts\JournalEntryRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class JournalEntryRepository implements JournalEntryRepositoryInterface
{
    public function __construct(
        private JournalEntry $model
    ) {}

    public function create(array $data): JournalEntry
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?JournalEntry
    {
        return $this->model->with(['lines.account', 'fiscalPeriod'])->find($id);
    }

    public function update(int $id, array $data): JournalEntry
    {
        $entry = $this->findById($id);
        $entry->update($data);
        return $entry->fresh();
    }

    public function getLastEntry(int $cooperativeId): ?JournalEntry
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->orderBy('reference_number', 'desc')
            ->first();
    }

    public function getByPeriod(int $fiscalPeriodId): Collection
    {
        return $this->model->where('fiscal_period_id', $fiscalPeriodId)
            ->with(['lines.account'])
            ->orderBy('transaction_date')
            ->get();
    }

    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('cooperative_id', $cooperativeId)
            ->with(['lines.account', 'fiscalPeriod']);

        if (!empty($filters['fiscal_period_id'])) {
            $query->where('fiscal_period_id', $filters['fiscal_period_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('transaction_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('transaction_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('transaction_date', 'desc')->paginate($perPage);
    }

    public function getAccountBalance(int $accountId, string $asOfDate): float
    {
        $lines = \DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entry_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entry_lines.account_id', $accountId)
            ->where('journal_entries.transaction_date', '<=', $asOfDate)
            ->selectRaw('SUM(debit_amount) as total_debit, SUM(credit_amount) as total_credit')
            ->first();

        return ($lines->total_debit ?? 0) - ($lines->total_credit ?? 0);
    }
}
