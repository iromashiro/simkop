<?php
// app/Http/Resources/Financial/AccountResource.php
namespace App\Http\Resources\Financial;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'category' => $this->category,
            'description' => $this->description,
            'is_header' => $this->is_header,
            'is_active' => $this->is_active,
            'normal_balance' => $this->normal_balance,
            'current_balance' => $this->current_balance,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // Relationships
            'parent' => $this->whenLoaded('parent', function () {
                return [
                    'id' => $this->parent->id,
                    'code' => $this->parent->code,
                    'name' => $this->parent->name,
                ];
            }),

            'children' => $this->whenLoaded('children', function () {
                return $this->children->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'code' => $child->code,
                        'name' => $child->name,
                        'current_balance' => $child->current_balance,
                        'is_active' => $child->is_active,
                    ];
                });
            }),

            // Statistics
            'statistics' => $this->when($this->relationLoaded('statistics'), function () {
                return [
                    'total_debits' => $this->statistics->total_debits ?? 0,
                    'total_credits' => $this->statistics->total_credits ?? 0,
                    'transaction_count' => $this->statistics->transaction_count ?? 0,
                    'last_transaction_date' => $this->statistics->last_transaction_date,
                ];
            }),
        ];
    }
}
