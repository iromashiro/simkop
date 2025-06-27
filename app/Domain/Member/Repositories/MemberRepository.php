<?php

namespace App\Domain\Member\Repositories;

use App\Domain\Member\Models\Member;
use App\Domain\Member\Contracts\MemberRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Member Repository Implementation
 *
 * Concrete implementation of member data access operations
 * Handles all database interactions for member entities
 *
 * @package App\Domain\Member\Repositories
 * @author Mateen (Senior Software Engineer)
 */
class MemberRepository implements MemberRepositoryInterface
{
    public function __construct(
        private Member $model
    ) {}

    /**
     * Create new member
     */
    public function create(array $data): Member
    {
        return $this->model->create($data);
    }

    /**
     * Find member by ID
     */
    public function findById(int $id): ?Member
    {
        return $this->model->with(['cooperative', 'user', 'savingsAccounts', 'loanAccounts'])
            ->find($id);
    }

    /**
     * Find member by member number
     */
    public function findByMemberNumber(string $memberNumber, int $cooperativeId): ?Member
    {
        return $this->model->where('member_number', $memberNumber)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }

    /**
     * Find member by email
     */
    public function findByEmail(string $email, int $cooperativeId): ?Member
    {
        return $this->model->where('email', $email)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }

    /**
     * Update member
     */
    public function update(int $id, array $data): Member
    {
        $member = $this->findById($id);
        $member->update($data);
        return $member->fresh();
    }

    /**
     * Delete member (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    /**
     * Get paginated members for cooperative
     */
    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->where('cooperative_id', $cooperativeId)
            ->with(['cooperative', 'user']);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['gender'])) {
            $query->where('gender', $filters['gender']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('member_number', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($filters['join_date_from'])) {
            $query->where('join_date', '>=', $filters['join_date_from']);
        }

        if (!empty($filters['join_date_to'])) {
            $query->where('join_date', '<=', $filters['join_date_to']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Search members by query
     */
    public function search(int $cooperativeId, string $query, array $filters = []): Collection
    {
        $searchQuery = $this->model->where('cooperative_id', $cooperativeId)
            ->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('member_number', 'ILIKE', "%{$query}%")
                    ->orWhere('email', 'ILIKE', "%{$query}%")
                    ->orWhere('phone', 'ILIKE', "%{$query}%");
            });

        // Apply additional filters
        if (!empty($filters['status'])) {
            $searchQuery->where('status', $filters['status']);
        }

        return $searchQuery->limit(50)->get();
    }

    /**
     * Get last member by cooperative for number generation
     */
    public function getLastMemberByCooperative(int $cooperativeId): ?Member
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->orderBy('member_number', 'desc')
            ->first();
    }

    /**
     * Count members by cooperative
     */
    public function countByCooperative(int $cooperativeId): int
    {
        return $this->model->where('cooperative_id', $cooperativeId)->count();
    }

    /**
     * Count members by status
     */
    public function countByStatus(int $cooperativeId, string $status): int
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('status', $status)
            ->count();
    }

    /**
     * Count new members this month
     */
    public function countNewMembersThisMonth(int $cooperativeId): int
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->whereMonth('join_date', now()->month)
            ->whereYear('join_date', now()->year)
            ->count();
    }

    /**
     * Get members by status
     */
    public function getByStatus(int $cooperativeId, string $status): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('status', $status)
            ->get();
    }

    /**
     * Get members with outstanding balances
     */
    public function getWithOutstandingBalances(int $cooperativeId): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->whereHas('loanAccounts', function ($query) {
                $query->where('outstanding_balance', '>', 0);
            })
            ->with(['loanAccounts' => function ($query) {
                $query->where('outstanding_balance', '>', 0);
            }])
            ->get();
    }

    /**
     * Bulk update member status
     */
    public function bulkUpdateStatus(array $memberIds, string $status, int $updatedBy): int
    {
        return $this->model->whereIn('id', $memberIds)
            ->update([
                'status' => $status,
                'status_updated_at' => now(),
                'status_updated_by' => $updatedBy,
                'updated_at' => now(),
            ]);
    }

    /**
     * Get member statistics
     */
    public function getStatistics(int $cooperativeId): array
    {
        $stats = $this->model->where('cooperative_id', $cooperativeId)
            ->selectRaw('
                                COUNT(*) as total_members,
                                COUNT(CASE WHEN status = ? THEN 1 END) as active_members,
                                COUNT(CASE WHEN status = ? THEN 1 END) as suspended_members,
                                COUNT(CASE WHEN status = ? THEN 1 END) as terminated_members,
                                COUNT(CASE WHEN DATE_TRUNC(\'month\', join_date) = DATE_TRUNC(\'month\', CURRENT_DATE) THEN 1 END) as new_members_this_month,
                                COUNT(CASE WHEN gender = ? THEN 1 END) as male_members,
                                COUNT(CASE WHEN gender = ? THEN 1 END) as female_members,
                                AVG(CASE WHEN monthly_income > 0 THEN monthly_income END) as avg_monthly_income
                            ', ['active', 'suspended', 'terminated', 'male', 'female'])
            ->first();

        return [
            'total_members' => (int) $stats->total_members,
            'active_members' => (int) $stats->active_members,
            'suspended_members' => (int) $stats->suspended_members,
            'terminated_members' => (int) $stats->terminated_members,
            'new_members_this_month' => (int) $stats->new_members_this_month,
            'male_members' => (int) $stats->male_members,
            'female_members' => (int) $stats->female_members,
            'avg_monthly_income' => (float) $stats->avg_monthly_income ?: 0,
        ];
    }

    /**
     * Find member by phone
     */
    public function findByPhone(string $phone, int $cooperativeId): ?Member
    {
        return $this->model->where('phone', $phone)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }

    /**
     * Find member by ID number
     */
    public function findByIdNumber(string $idNumber, int $cooperativeId): ?Member
    {
        return $this->model->where('id_number', $idNumber)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }

    /**
     * Find members by IDs
     */
    public function findByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)->get();
    }
}
