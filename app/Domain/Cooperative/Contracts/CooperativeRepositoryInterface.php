<?php

namespace App\Domain\Cooperative\Contracts;

use App\Domain\Cooperative\Models\Cooperative;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Cooperative Repository Interface
 *
 * Defines contract for cooperative data access operations
 *
 * @package App\Domain\Cooperative\Contracts
 * @author Mateen (Senior Software Engineer)
 */
interface CooperativeRepositoryInterface
{
    public function create(array $data): Cooperative;
    public function findById(int $id): ?Cooperative;
    public function findByCode(string $code): ?Cooperative;
    public function findByRegistrationNumber(string $registrationNumber): ?Cooperative;
    public function findByEmail(string $email): ?Cooperative;
    public function update(int $id, array $data): Cooperative;
    public function delete(int $id): bool;
    public function getAll(): Collection;
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator;
    public function getStatistics(): array;
    public function search(string $query, array $filters = []): Collection;
}
