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

        // Global scope for non-admin users
        static::addGlobalScope('cooperative', function (Builder $builder) {
            if (auth()->check() && !auth()->user()->isAdminDinas()) {
                $builder->where('cooperative_id', auth()->user()->cooperative_id);
            }
        });
    }

    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    public function scopeByCooperative(Builder $query, int $cooperativeId)
    {
        return $query->where('cooperative_id', $cooperativeId);
    }
}
