<?php
// app/Http/Resources/Cooperative/CooperativeCollection.php
namespace App\Http\Resources\Cooperative;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class CooperativeCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_cooperatives' => $this->collection->count(),
                'active_cooperatives' => $this->collection->where('status', 'active')->count(),
                'total_members' => $this->collection->sum('total_members'),
                'total_assets' => $this->collection->sum('total_assets'),
            ],
        ];
    }
}
