<?php

namespace App\Domain\Cooperative\Services;

use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Cooperative\DTOs\CreateCooperativeDTO;
use App\Domain\Cooperative\DTOs\UpdateCooperativeDTO;
use App\Domain\Cooperative\Contracts\CooperativeRepositoryInterface;
use App\Domain\Cooperative\Exceptions\CooperativeNotFoundException;
use App\Domain\Cooperative\Exceptions\CooperativeValidationException;
use App\Domain\Cooperative\Events\CooperativeCreated;
use App\Domain\Cooperative\Events\CooperativeUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Collection;

class CooperativeService
{
    public function __construct(
        private CooperativeRepositoryInterface $cooperativeRepository
    ) {}

    public function createCooperative(CreateCooperativeDTO $dto): Cooperative
    {
        return DB::transaction(function () use ($dto) {
            $this->validateUniqueFields($dto);

            $cooperative = $this->cooperativeRepository->create([
                'name' => $dto->name,
                'code' => $dto->code,
                'registration_number' => $dto->registration_number,
                'address' => $dto->address,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'website' => $dto->website,
                'established_date' => $dto->established_date->format('Y-m-d'),
                'legal_entity_type' => $dto->legal_entity_type,
                'business_type' => $dto->business_type,
                'chairman_name' => $dto->chairman_name,
                'secretary_name' => $dto->secretary_name,
                'treasurer_name' => $dto->treasurer_name,
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            $this->setupCooperativeAccounts($cooperative);
            $this->clearCooperativeCache();

            event(new CooperativeCreated($cooperative, auth()->id()));

            Log::info('Cooperative created', [
                'cooperative_id' => $cooperative->id,
                'code' => $cooperative->code,
                'created_by' => auth()->id()
            ]);

            return $cooperative;
        });
    }

    public function updateCooperative(int $id, UpdateCooperativeDTO $dto): Cooperative
    {
        return DB::transaction(function () use ($id, $dto) {
            $cooperative = $this->findById($id);
            if (!$cooperative) {
                throw CooperativeNotFoundException::forId($id);
            }

            $originalData = $cooperative->toArray();
            $this->validateUniqueFieldsForUpdate($cooperative, $dto);

            $updateData = array_filter([
                'name' => $dto->name,
                'address' => $dto->address,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'website' => $dto->website,
                'chairman_name' => $dto->chairman_name,
                'secretary_name' => $dto->secretary_name,
                'treasurer_name' => $dto->treasurer_name,
                'updated_by' => auth()->id(),
            ], fn($value) => $value !== null);

            if (empty($updateData)) {
                return $cooperative;
            }

            $cooperative = $this->cooperativeRepository->update($id, $updateData);
            $this->clearCooperativeCache();

            event(new CooperativeUpdated($cooperative, $originalData, $updateData, auth()->id()));

            Log::info('Cooperative updated', [
                'cooperative_id' => $id,
                'updated_fields' => array_keys($updateData),
                'updated_by' => auth()->id()
            ]);

            return $cooperative;
        });
    }

    public function findById(int $id): ?Cooperative
    {
        return $this->cooperativeRepository->findById($id);
    }

    public function findByCode(string $code): ?Cooperative
    {
        return $this->cooperativeRepository->findByCode($code);
    }

    public function hasAccess(int $cooperativeId): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        if ($user->hasRole('super_admin')) return true;

        return $user->cooperatives()->where('cooperatives.id', $cooperativeId)->exists();
    }

    public function getAccessibleCooperatives(): Collection
    {
        $user = auth()->user();
        if (!$user) return collect();

        if ($user->hasRole('super_admin')) {
            return $this->cooperativeRepository->getAll();
        }

        return $user->cooperatives;
    }

    private function validateUniqueFields(CreateCooperativeDTO $dto): void
    {
        if ($this->cooperativeRepository->findByCode($dto->code)) {
            throw CooperativeValidationException::duplicateField('code', $dto->code);
        }

        if ($this->cooperativeRepository->findByRegistrationNumber($dto->registration_number)) {
            throw CooperativeValidationException::duplicateField('registration_number', $dto->registration_number);
        }

        if ($dto->email && $this->cooperativeRepository->findByEmail($dto->email)) {
            throw CooperativeValidationException::duplicateField('email', $dto->email);
        }
    }

    private function validateUniqueFieldsForUpdate(Cooperative $cooperative, UpdateCooperativeDTO $dto): void
    {
        if ($dto->email && $dto->email !== $cooperative->email) {
            $existing = $this->cooperativeRepository->findByEmail($dto->email);
            if ($existing && $existing->id !== $cooperative->id) {
                throw CooperativeValidationException::duplicateField('email', $dto->email);
            }
        }
    }

    private function setupCooperativeAccounts(Cooperative $cooperative): void
    {
        Log::info('Setting up cooperative accounts', ['cooperative_id' => $cooperative->id]);
    }

    private function clearCooperativeCache(): void
    {
        Cache::tags(['cooperatives'])->flush();
    }
}
