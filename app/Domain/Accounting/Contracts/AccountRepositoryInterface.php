<?php

namespace App\Domain\Accounting\Contracts;

use App\Domain\Accounting\Models\Account;
use Illuminate\Database\Eloquent\Collection;

interface AccountRepositoryInterface
{
    public function create(array $data): Account;
    public function findById(int $id): ?Account;
    public function findByCode(string $code, int $cooperativeId): ?Account;
    public function update(int $id, array $data): Account;
    public function delete(int $id): bool;
    public function getByType(int $cooperativeId, string $type): Collection;
    public function getHierarchy(int $cooperativeId): array;
    public function getByCooperative(int $cooperativeId): Collection;
    public function getBalance(int $accountId, string $asOfDate): float;
}
