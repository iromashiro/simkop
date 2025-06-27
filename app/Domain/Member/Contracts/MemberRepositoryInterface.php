<?php

namespace App\Domain\Member\Contracts;

use App\Domain\Member\Models\Member;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Member Repository Interface
 *
 * Defines contract for member data access operations
 * Enables dependency injection and testing flexibility
 *
 * @package App\Domain\Member\Contracts
 * @author Mateen (Senior Software Engineer)
 */
interface MemberRepositoryInterface
{
    /**
     * Create new member
     *
     * @param array $data
     * @return Member
     */
    public function create(array $data): Member;

    /**
     * Find member by ID
     *
     * @param int $id
     * @return Member|null
     */
    public function findById(int $id): ?Member;

    /**
     * Find member by member number
     *
     * @param string $memberNumber
     * @param int $cooperativeId
     * @return Member|null
     */
    public function findByMemberNumber(string $memberNumber, int $cooperativeId): ?Member;

    /**
     * Find member by email
     *
     * @param string $email
     * @param int $cooperativeId
     * @return Member|null
     */
    public function findByEmail(string $email, int $cooperativeId): ?Member;

    /**
     * Update member
     *
     * @param int $id
     * @param array $data
     * @return Member
     */
    public function update(int $id, array $data): Member;

    /**
     * Delete member (soft delete)
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool;

    /**
     * Get paginated members for cooperative
     *
     * @param int $cooperativeId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    /**
     * Search members by query
     *
     * @param int $cooperativeId
     * @param string $query
     * @param array $filters
     * @return Collection
     */
    public function search(int $cooperativeId, string $query, array $filters = []): Collection;

    /**
     * Get last member by cooperative for number generation
     *
     * @param int $cooperativeId
     * @return Member|null
     */
    public function getLastMemberByCooperative(int $cooperativeId): ?Member;

    /**
     * Count members by cooperative
     *
     * @param int $cooperativeId
     * @return int
     */
    public function countByCooperative(int $cooperativeId): int;

    /**
     * Count members by status
     *
     * @param int $cooperativeId
     * @param string $status
     * @return int
     */
    public function countByStatus(int $cooperativeId, string $status): int;

    /**
     * Count new members this month
     *
     * @param int $cooperativeId
     * @return int
     */
    public function countNewMembersThisMonth(int $cooperativeId): int;

    /**
     * Get members by status
     *
     * @param int $cooperativeId
     * @param string $status
     * @return Collection
     */
    public function getByStatus(int $cooperativeId, string $status): Collection;

    /**
     * Get members with outstanding balances
     *
     * @param int $cooperativeId
     * @return Collection
     */
    public function getWithOutstandingBalances(int $cooperativeId): Collection;

    /**
     * Bulk update member status
     *
     * @param array $memberIds
     * @param string $status
     * @param int $updatedBy
     * @return int Number of updated records
     */
    public function bulkUpdateStatus(array $memberIds, string $status, int $updatedBy): int;

    /**
     * Get member statistics
     *
     * @param int $cooperativeId
     * @return array
     */
    public function getStatistics(int $cooperativeId): array;
}
