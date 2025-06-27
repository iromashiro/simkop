<?php

namespace App\Domain\Cooperative\Repositories;

use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Cooperative\Contracts\CooperativeRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Cooperative Repository Implementation
 *
 * Concrete implementation of cooperative data access operations
 * Handles all database interactions for cooperative entities
 *
 * @package App\Domain\Cooperative\Repositories
 * @author Mateen (Senior Software Engineer)
 */
class CooperativeRepository implements CooperativeRepositoryInterface
{
    public function __construct(
        private Cooperative $model
    ) {}

    /**
     * Create new cooperative
     */
    public function create(array $data): Cooperative
    {
        return $this->model->create($data);
    }

    /**
     * Find cooperative by ID
     */
    public function findById(int $id): ?Cooperative
    {
        return $this->model->with(['members', 'users'])->find($id);
    }

    /**
     * Find cooperative by code
     */
    public function findByCode(string $code): ?Cooperative
    {
        return $this->model->where('code', $code)->first();
    }

    /**
     * Find cooperative by registration number
     */
    public function findByRegistrationNumber(string $registrationNumber): ?Cooperative
    {
        return $this->model->where('registration_number', $registrationNumber)->first();
    }

    /**
     * Find cooperative by email
     */
    public function findByEmail(string $email): ?Cooperative
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Update cooperative
     */
    public function update(int $id, array $data): Cooperative
    {
        $cooperative = $this->findById($id);
        $cooperative->update($data);
        return $cooperative->fresh();
    }

    /**
     * Delete cooperative (soft delete)
     */
    public function delete(int $id): bool
    {
        return $this->model->destroy($id) > 0;
    }

    /**
     * Get all cooperatives
     */
    public function getAll(): Collection
    {
        return $this->model->where('is_active', true)->get();
    }

    /**
     * Get paginated cooperatives
     */
    public function getPaginated(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('registration_number', 'ILIKE', "%{$search}%");
            });
        }

        if (!empty($filters['legal_entity_type'])) {
            $query->where('legal_entity_type', $filters['legal_entity_type']);
        }

        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get cooperative statistics
     */
    public function getStatistics(): array
    {
        $stats = $this->model->selectRaw('
            COUNT(*) as total_cooperatives,
            COUNT(CASE WHEN is_active = true THEN 1 END) as active_cooperatives,
            COUNT(CASE WHEN is_active = false THEN 1 END) as inactive_cooperatives,
            COUNT(CASE WHEN DATE_TRUNC(\'month\', created_at) = DATE_TRUNC(\'month\', CURRENT_DATE) THEN 1 END) as new_cooperatives_this_month
        ')->first();

        return [
            'total_cooperatives' => (int) $stats->total_cooperatives,
            'active_cooperatives' => (int) $stats->active_cooperatives,
            'inactive_cooperatives' => (int) $stats->inactive_cooperatives,
            'new_cooperatives_this_month' => (int) $stats->new_cooperatives_this_month,
        ];
    }

    /**
     * Search cooperatives
     */
    public function search(string $query, array $filters = []): Collection
    {
        $searchQuery = $this->model->where(function ($q) use ($query) {
            $q->where('name', 'ILIKE', "%{$query}%")
                ->orWhere('code', 'ILIKE', "%{$query}%")
                ->orWhere('registration_number', 'ILIKE', "%{$query}%");
        });

        if (!empty($filters['is_active'])) {
            $searchQuery->where('is_active', $filters['is_active']);
        }

        return $searchQuery->limit(50)->get();
    }
}
