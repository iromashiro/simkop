<?php
// app/Http/Resources/Financial/JournalEntryResource.php
namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'entry_number' => $this->entry_number,
            'entry_date' => $this->entry_date?->format('Y-m-d'),
            'description' => $this->description,
            'reference' => $this->reference,
            'status' => $this->status,
            'total_amount' => $this->total_amount,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),

            // Relationships
            'fiscal_period' => $this->whenLoaded('fiscalPeriod', function () {
                return [
                    'id' => $this->fiscalPeriod->id,
                    'name' => $this->fiscalPeriod->name,
                    'start_date' => $this->fiscalPeriod->start_date->format('Y-m-d'),
                    'end_date' => $this->fiscalPeriod->end_date->format('Y-m-d'),
                ];
            }),

            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),

            'approved_by' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ];
            }),

            'lines' => $this->whenLoaded('lines', function () {
                return $this->lines->map(function ($line) {
                    return [
                        'id' => $line->id,
                        'account' => [
                            'id' => $line->account->id,
                            'code' => $line->account->code,
                            'name' => $line->account->name,
                        ],
                        'description' => $line->description,
                        'debit_amount' => $line->debit_amount,
                        'credit_amount' => $line->credit_amount,
                    ];
                });
            }),

            'reversal_entry' => $this->whenLoaded('reversalEntry', function () {
                return [
                    'id' => $this->reversalEntry->id,
                    'entry_number' => $this->reversalEntry->entry_number,
                    'entry_date' => $this->reversalEntry->entry_date->format('Y-m-d'),
                ];
            }),
        ];
    }
}
