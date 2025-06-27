<?php

namespace App\Domain\Loan\Repositories;

use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Loan\Contracts\LoanRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class LoanRepository implements LoanRepositoryInterface
{
    public function __construct(
        private LoanAccount $model
    ) {}

    public function create(array $data): LoanAccount
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?LoanAccount
    {
        return $this->model->with(['member', 'payments'])->find($id);
    }

    public function update(int $id, array $data): LoanAccount
    {
        $loan = $this->findById($id);
        $loan->update($data);
        return $loan->fresh();
    }

    public function getLastLoan(int $cooperativeId): ?LoanAccount
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->orderBy('loan_number', 'desc')
            ->first();
    }

    public function getByMember(int $memberId): Collection
    {
        return $this->model->where('member_id', $memberId)
            ->with(['payments'])
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

        if (!empty($filters['loan_type'])) {
            $query->where('loan_type', $filters['loan_type']);
        }

        if (!empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    public function getOutstandingLoans(int $cooperativeId): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('outstanding_balance', '>', 0)
            ->where('status', 'active')
            ->with(['member'])
            ->get();
    }

    public function getTotalOutstanding(int $cooperativeId): float
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->sum('outstanding_balance');
    }

    public function findByLoanNumber(string $loanNumber, int $cooperativeId): ?LoanAccount
    {
        return $this->model->where('loan_number', $loanNumber)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }
}
