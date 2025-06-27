<?php

namespace App\Domain\Savings\Repositories;

use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Contracts\SavingsRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class SavingsRepository implements SavingsRepositoryInterface
{
    public function __construct(
        private SavingsAccount $model
    ) {}

    public function create(array $data): SavingsAccount
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?SavingsAccount
    {
        return $this->model->with(['member', 'transactions'])->find($id);
    }

    public function update(int $id, array $data): SavingsAccount
    {
        $account = $this->findById($id);
        $account->update($data);
        return $account->fresh();
    }

    public function getLastAccount(int $cooperativeId): ?SavingsAccount
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->orderBy('account_number', 'desc')
            ->first();
    }

    public function getByMember(int $memberId): Collection
    {
        return $this->model->where('member_id', $memberId)
            ->with(['transactions'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getByCooperative(int $cooperativeId): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->with(['member'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('cooperative_id', $cooperativeId)
            ->with(['member']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['account_type'])) {
            $query->where('account_type', $filters['account_type']);
        }

        if (!empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getTotalBalance(int $cooperativeId): float
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->sum('balance');
    }

    public function getAccountStatistics(int $cooperativeId): array
    {
        $stats = $this->model->where('cooperative_id', $cooperativeId)
            ->selectRaw('
                                COUNT(*) as total_accounts,
                                COUNT(CASE WHEN status = ? THEN 1 END) as active_accounts,
                                SUM(CASE WHEN status = ? THEN balance ELSE 0 END) as total_balance,
                                AVG(CASE WHEN status = ? THEN balance ELSE NULL END) as average_balance
                            ', ['active', 'active', 'active'])
            ->first();

        return [
            'total_accounts' => (int) $stats->total_accounts,
            'active_accounts' => (int) $stats->active_accounts,
            'total_balance' => (float) $stats->total_balance,
            'average_balance' => (float) $stats->average_balance,
        ];
    }

    public function findByAccountNumber(string $accountNumber, int $cooperativeId): ?SavingsAccount
    {
        return $this->model->where('account_number', $accountNumber)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }
}
