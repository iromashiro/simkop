<?php

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\DTOs\CreateFiscalPeriodDTO;
use App\Domain\Accounting\Contracts\FiscalPeriodRepositoryInterface;
use App\Domain\Accounting\Exceptions\FiscalPeriodValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FiscalPeriodService
{
    public function __construct(
        private FiscalPeriodRepositoryInterface $fiscalPeriodRepository
    ) {}

    public function createFiscalPeriod(CreateFiscalPeriodDTO $dto): FiscalPeriod
    {
        return DB::transaction(function () use ($dto) {
            $this->validatePeriodOverlap($dto);

            $fiscalPeriod = $this->fiscalPeriodRepository->create([
                'cooperative_id' => $dto->cooperative_id,
                'name' => $dto->name,
                'start_date' => $dto->start_date->format('Y-m-d'),
                'end_date' => $dto->end_date->format('Y-m-d'),
                'is_active' => true,
                'created_by' => auth()->id(),
            ]);

            Log::info('Fiscal period created', [
                'fiscal_period_id' => $fiscalPeriod->id,
                'cooperative_id' => $fiscalPeriod->cooperative_id
            ]);

            return $fiscalPeriod;
        });
    }

    public function getActivePeriod(int $cooperativeId): ?FiscalPeriod
    {
        return $this->fiscalPeriodRepository->getActive($cooperativeId);
    }

    public function closePeriod(int $id): FiscalPeriod
    {
        return DB::transaction(function () use ($id) {
            $period = $this->fiscalPeriodRepository->findById($id);
            if (!$period) {
                throw new \Exception('Fiscal period not found');
            }

            $period = $this->fiscalPeriodRepository->update($id, [
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            Log::info('Fiscal period closed', ['fiscal_period_id' => $id]);

            return $period;
        });
    }

    private function validatePeriodOverlap(CreateFiscalPeriodDTO $dto): void
    {
        $overlapping = $this->fiscalPeriodRepository->findOverlapping(
            $dto->cooperative_id,
            $dto->start_date,
            $dto->end_date
        );

        if ($overlapping->isNotEmpty()) {
            throw FiscalPeriodValidationException::overlappingPeriod($dto->start_date, $dto->end_date);
        }
    }
}
