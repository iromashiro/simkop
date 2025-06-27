<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\DTOs\CreateAccountDTO;
use App\Domain\Accounting\DTOs\UpdateAccountDTO;
use App\Domain\Accounting\Contracts\AccountRepositoryInterface;
use App\Domain\Accounting\Exceptions\AccountNotFoundException;
use App\Domain\Accounting\Exceptions\AccountValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class AccountService
{
    public function __construct(
        private AccountRepositoryInterface $accountRepository
    ) {}

    public function createAccount(CreateAccountDTO $dto): Account
    {
        return DB::transaction(function () use ($dto) {
            $this->validateAccountHierarchy($dto);
            $this->validateUniqueCode($dto->code, $dto->cooperative_id);

            $account = $this->accountRepository->create([
                'cooperative_id' => $dto->cooperative_id,
                'code' => $dto->code,
                'name' => $dto->name,
                'type' => $dto->type,
                'parent_id' => $dto->parent_id,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $this->clearAccountCache($dto->cooperative_id);

            Log::info('Account created', [
                'account_id' => $account->id,
                'code' => $account->code,
                'cooperative_id' => $account->cooperative_id
            ]);

            return $account;
        });
    }

    public function updateAccount(int $id, UpdateAccountDTO $dto): Account
    {
        return DB::transaction(function () use ($id, $dto) {
            $account = $this->findById($id);
            if (!$account) {
                throw AccountNotFoundException::forId($id);
            }

            if ($dto->code && $dto->code !== $account->code) {
                $this->validateUniqueCode($dto->code, $account->cooperative_id, $id);
            }

            $updateData = array_filter([
                'name' => $dto->name,
                'code' => $dto->code,
                'updated_by' => auth()->id(),
            ], fn($value) => $value !== null);

            if (empty($updateData)) {
                return $account;
            }

            $account = $this->accountRepository->update($id, $updateData);
            $this->clearAccountCache($account->cooperative_id);

            return $account;
        });
    }

    public function findById(int $id): ?Account
    {
        return $this->accountRepository->findById($id);
    }

    public function getAccountsByType(int $cooperativeId, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return Cache::tags(['accounts', "cooperative_{$cooperativeId}"])
            ->remember("accounts_{$cooperativeId}_{$type}", 300, function () use ($cooperativeId, $type) {
                return $this->accountRepository->getByType($cooperativeId, $type);
            });
    }

    public function getAccountHierarchy(int $cooperativeId): array
    {
        return Cache::tags(['accounts', "cooperative_{$cooperativeId}"])
            ->remember("account_hierarchy_{$cooperativeId}", 600, function () use ($cooperativeId) {
                return $this->accountRepository->getHierarchy($cooperativeId);
            });
    }

    private function validateAccountHierarchy(CreateAccountDTO $dto): void
    {
        if ($dto->parent_id) {
            $parent = $this->accountRepository->findById($dto->parent_id);
            if (!$parent || $parent->cooperative_id !== $dto->cooperative_id) {
                throw AccountValidationException::invalidParent($dto->parent_id);
            }
        }
    }

    private function validateUniqueCode(string $code, int $cooperativeId, ?int $excludeId = null): void
    {
        $existing = $this->accountRepository->findByCode($code, $cooperativeId);
        if ($existing && (!$excludeId || $existing->id !== $excludeId)) {
            throw AccountValidationException::duplicateCode($code);
        }
    }

    private function clearAccountCache(int $cooperativeId): void
    {
        Cache::tags(["cooperative_{$cooperativeId}", 'accounts'])->flush();
    }
}
