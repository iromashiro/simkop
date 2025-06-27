<?php

namespace App\Domain\Member\Services;

use App\Domain\Member\Models\Member;
use App\Domain\Member\DTOs\CreateMemberDTO;
use App\Domain\Member\DTOs\UpdateMemberDTO;
use App\Domain\Member\Contracts\MemberRepositoryInterface;
use App\Domain\Cooperative\Services\CooperativeService;
use App\Domain\Auth\Services\UserService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Member Service - Handles all member-related business logic
 *
 * Manages member registration, updates, status changes, and cooperative membership
 * Implements double-entry bookkeeping for member equity accounts
 *
 * @package App\Domain\Member\Services
 * @author Mateen (Senior Software Engineer)
 */
class MemberService
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository,
        private CooperativeService $cooperativeService,
        private UserService $userService
    ) {}

    /**
     * Create new member with complete validation and equity setup
     *
     * @param CreateMemberDTO $dto
     * @return Member
     * @throws \Exception
     */
    public function createMember(CreateMemberDTO $dto): Member
    {
        return DB::transaction(function () use ($dto) {
            // Validate cooperative exists and is active
            $cooperative = $this->cooperativeService->findById($dto->cooperative_id);
            if (!$cooperative || !$cooperative->is_active) {
                throw new \Exception('Cooperative not found or inactive');
            }

            // Generate member number
            $memberNumber = $this->generateMemberNumber($dto->cooperative_id);

            // Create member
            $member = $this->memberRepository->create([
                'cooperative_id' => $dto->cooperative_id,
                'member_number' => $memberNumber,
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'id_number' => $dto->id_number,
                'id_type' => $dto->id_type,
                'date_of_birth' => $dto->date_of_birth,
                'gender' => $dto->gender,
                'occupation' => $dto->occupation,
                'monthly_income' => $dto->monthly_income,
                'emergency_contact_name' => $dto->emergency_contact_name,
                'emergency_contact_phone' => $dto->emergency_contact_phone,
                'join_date' => $dto->join_date ?? now(),
                'status' => 'active',
                'created_by' => auth()->id(),
            ]);

            // Create user account if email provided
            if ($dto->email && $dto->create_user_account) {
                $this->userService->createMemberUser($member, $dto->password);
            }

            // Setup member equity accounts
            $this->setupMemberEquityAccounts($member);

            // Log member creation
            Log::info('Member created successfully', [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'cooperative_id' => $member->cooperative_id,
                'created_by' => auth()->id()
            ]);

            return $member;
        });
    }

    /**
     * Update existing member
     *
     * @param int $id
     * @param UpdateMemberDTO $dto
     * @return Member
     * @throws \Exception
     */
    public function updateMember(int $id, UpdateMemberDTO $dto): Member
    {
        return DB::transaction(function () use ($id, $dto) {
            $member = $this->findById($id);

            if (!$member) {
                throw new \Exception('Member not found');
            }

            // Validate cooperative access
            $this->validateCooperativeAccess($member->cooperative_id);

            $updateData = array_filter([
                'name' => $dto->name,
                'email' => $dto->email,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'occupation' => $dto->occupation,
                'monthly_income' => $dto->monthly_income,
                'emergency_contact_name' => $dto->emergency_contact_name,
                'emergency_contact_phone' => $dto->emergency_contact_phone,
                'updated_by' => auth()->id(),
            ]);

            $member = $this->memberRepository->update($id, $updateData);

            Log::info('Member updated successfully', [
                'member_id' => $member->id,
                'updated_fields' => array_keys($updateData),
                'updated_by' => auth()->id()
            ]);

            return $member;
        });
    }

    /**
     * Find member by ID with cooperative validation
     *
     * @param int $id
     * @return Member|null
     */
    public function findById(int $id): ?Member
    {
        $member = $this->memberRepository->findById($id);

        if ($member) {
            $this->validateCooperativeAccess($member->cooperative_id);
        }

        return $member;
    }

    /**
     * Get paginated members for cooperative
     *
     * @param int $cooperativeId
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedMembers(int $cooperativeId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $this->validateCooperativeAccess($cooperativeId);

        return $this->memberRepository->getPaginated($cooperativeId, $filters, $perPage);
    }

    /**
     * Activate member
     *
     * @param int $id
     * @return Member
     */
    public function activateMember(int $id): Member
    {
        return $this->updateMemberStatus($id, 'active');
    }

    /**
     * Suspend member
     *
     * @param int $id
     * @param string $reason
     * @return Member
     */
    public function suspendMember(int $id, string $reason = ''): Member
    {
        $member = $this->updateMemberStatus($id, 'suspended');

        if ($reason) {
            $this->memberRepository->update($id, ['suspension_reason' => $reason]);
        }

        return $member;
    }

    /**
     * Terminate member membership
     *
     * @param int $id
     * @param string $reason
     * @return Member
     */
    public function terminateMember(int $id, string $reason = ''): Member
    {
        return DB::transaction(function () use ($id, $reason) {
            // Check for outstanding balances
            $member = $this->findById($id);
            $outstandingBalance = $this->calculateOutstandingBalance($member);

            if ($outstandingBalance > 0) {
                throw new \Exception('Cannot terminate member with outstanding balance: ' . number_format($outstandingBalance, 2));
            }

            $member = $this->updateMemberStatus($id, 'terminated');

            if ($reason) {
                $this->memberRepository->update($id, [
                    'termination_reason' => $reason,
                    'termination_date' => now()
                ]);
            }

            return $member;
        });
    }

    /**
     * Generate unique member number
     *
     * @param int $cooperativeId
     * @return string
     */
    private function generateMemberNumber(int $cooperativeId): string
    {
        $cooperative = $this->cooperativeService->findById($cooperativeId);
        $prefix = strtoupper(substr($cooperative->code, 0, 3));

        $lastMember = $this->memberRepository->getLastMemberByCooperative($cooperativeId);
        $nextNumber = $lastMember ? (int)substr($lastMember->member_number, -6) + 1 : 1;

        return $prefix . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Setup member equity accounts
     *
     * @param Member $member
     * @return void
     */
    private function setupMemberEquityAccounts(Member $member): void
    {
        // This will be implemented when AccountService is created
        // Creates member equity accounts for savings, loans, etc.
    }

    /**
     * Update member status
     *
     * @param int $id
     * @param string $status
     * @return Member
     */
    private function updateMemberStatus(int $id, string $status): Member
    {
        $member = $this->findById($id);

        if (!$member) {
            throw new \Exception('Member not found');
        }

        $member = $this->memberRepository->update($id, [
            'status' => $status,
            'status_updated_at' => now(),
            'status_updated_by' => auth()->id()
        ]);

        Log::info('Member status updated', [
            'member_id' => $id,
            'old_status' => $member->getOriginal('status'),
            'new_status' => $status,
            'updated_by' => auth()->id()
        ]);

        return $member;
    }

    /**
     * Calculate member outstanding balance
     *
     * @param Member $member
     * @return float
     */
    private function calculateOutstandingBalance(Member $member): float
    {
        // This will be implemented when LoanService and SavingsService are integrated
        return 0.0;
    }

    /**
     * Validate cooperative access for current user
     *
     * @param int $cooperativeId
     * @throws \Exception
     */
    private function validateCooperativeAccess(int $cooperativeId): void
    {
        if (!$this->cooperativeService->hasAccess($cooperativeId)) {
            throw new \Exception('Access denied to cooperative');
        }
    }

    /**
     * Search members by criteria
     *
     * @param int $cooperativeId
     * @param string $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchMembers(int $cooperativeId, string $query, array $filters = [])
    {
        $this->validateCooperativeAccess($cooperativeId);

        return $this->memberRepository->search($cooperativeId, $query, $filters);
    }

    /**
     * Get member statistics
     *
     * @param int $cooperativeId
     * @return array
     */
    public function getMemberStatistics(int $cooperativeId): array
    {
        $this->validateCooperativeAccess($cooperativeId);

        return [
            'total_members' => $this->memberRepository->countByCooperative($cooperativeId),
            'active_members' => $this->memberRepository->countByStatus($cooperativeId, 'active'),
            'suspended_members' => $this->memberRepository->countByStatus($cooperativeId, 'suspended'),
            'terminated_members' => $this->memberRepository->countByStatus($cooperativeId, 'terminated'),
            'new_members_this_month' => $this->memberRepository->countNewMembersThisMonth($cooperativeId),
        ];
    }
}
