<?php

namespace App\Domain\Accounting\Repositories;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Account Repository Implementation
 *
 * @package App\Domain\Accounting\Repositories
 * @author Mateen (Senior Software Engineer)
 */
class AccountRepository implements AccountRepositoryInterface
{
    public function __construct(
        private Account $model
    ) {}

    public function create(array $data): Account
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?Account
    {
        return $this->model->with(['parent', 'children'])->find($id);
    }

    public function findByCode(string $code, int $cooperativeId): ?Account
    {
        return $this->model->where('code', $code)
            ->where('cooperative_id', $cooperativeId)
            ->first();
    }

    public function update(int $id, array $data): Account
    {
        $account = $this->findById($id);
        $account->update($data);
        return $account->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    public function getByType(int $cooperativeId, string $type): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getHierarchy(int $cooperativeId): array
    {
        $accounts = $this->model->where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return $this->buildHierarchy($accounts);
    }

    public function getByCooperative(int $cooperativeId): Collection
    {
        return $this->model->where('cooperative_id', $cooperativeId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function getBalance(int $accountId, string $asOfDate): float
    {
        // This would calculate account balance from journal entries
        // Implementation depends on journal entry structure
        return 0.0;
    }

    private function buildHierarchy(Collection $accounts, ?int $parentId = null): array
    {
        $hierarchy = [];

        foreach ($accounts->where('parent_id', $parentId) as $account) {
            $accountData = $account->toArray();
            $accountData['children'] = $this->buildHierarchy($accounts, $account->id);
            $hierarchy[] = $accountData;
        }

        return $hierarchy;
    }
}
