<?php

namespace App\Traits;

use App\Models\Cooperative;
use Illuminate\Database\Eloquent\Builder;

trait BelongsToCooperative
{
    protected static function bootBelongsToCooperative()
    {
        // Auto-assign cooperative_id when creating
        static::creating(function ($model) {
            if (!$model->cooperative_id && auth()->check() && auth()->user()->cooperative_id) {
                $model->cooperative_id = auth()->user()->cooperative_id;
            }
        });

        // ✅ FIXED: Remove global scope - use conditional scope instead
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    // ✅ FIXED: Use conditional scope instead of global scope
    public function scopeForCurrentUser(Builder $query)
    {
        if (auth()->check() && !auth()->user()->isAdminDinas()) {
            return $query->where('cooperative_id', auth()->user()->cooperative_id);
        }
        return $query;
    }

    public function scopeByCooperative(Builder $query, int $cooperativeId)
    {
        return $query->where('cooperative_id', $cooperativeId);
    }

    // ✅ ADDED: Helper method to check access
    public function canBeAccessedByCurrentUser(): bool
    {
        if (!auth()->check()) {
            return false;
        }

        if (auth()->user()->isAdminDinas()) {
            return true;
        }

        return $this->cooperative_id === auth()->user()->cooperative_id;
    }
}
