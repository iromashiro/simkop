<?php
// app/Http/Resources/Member/MemberCollection.php
namespace App\Http\Resources\Member;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MemberCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total_members' => $this->collection->count(),
                'active_members' => $this->collection->where('status', 'active')->count(),
                'total_savings' => $this->collection->sum('total_savings'),
                'total_loans' => $this->collection->sum('total_loans'),
                'average_savings' => $this->collection->avg('total_savings'),
                'average_loans' => $this->collection->avg('total_loans'),
            ],
        ];
    }
}
