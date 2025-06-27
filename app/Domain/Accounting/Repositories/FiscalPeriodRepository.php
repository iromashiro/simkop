<?php

namespace App\Domain\Accounting\Repositories;

use App\Domain\Accounting\Contracts\FiscalPeriodRepositoryInterface;
use App\Domain\Accounting\Models\FiscalPeriod;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class FiscalPeriodRepository implements FiscalPeriodRepositoryInterface
{
    public function __construct(
        private FiscalPeriod $model
    ) {}

    public function create(array $data): FiscalPeriod
    {
        return $this->model->create($data);
    }

    public function findById(int $id): ?FiscalPeriod
    {
        return $this->model->find($id);
    }

    public function update(int $id, array $data): FiscalPeriod
    {
        $fiscalPeriod = $this->model->findOrFail($id);
        $fiscalPeriod->update($data);
        return $fiscalPeriod->fresh();
    }

    public function getActive(int $cooperativeId): ?FiscalPeriod
    {
        return $this->model
            ->where('cooperative_id', $cooperativeId)
            ->where('status', 'active')
            ->first();
    }

    public function getByCooperative(int $cooperativeId): Collection
    {
        return $this->model
            ->where('cooperative_id', $cooperativeId)
            ->get();
    }

    public function findOverlapping(int $cooperativeId, Carbon $startDate, Carbon $endDate): Collection
    {
        return $this->model
            ->where('cooperative_id', $cooperativeId)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q) use ($startDate, $endDate) {
                        $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            })
            ->get();
    }
}
