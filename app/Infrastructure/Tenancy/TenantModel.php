<?php

namespace App\Infrastructure\Tenancy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

/**
 * Tenant Model Base Class
 *
 * Provides multi-tenant functionality for all domain models
 * Automatically scopes queries by cooperative_id for data isolation
 *
 * @package App\Infrastructure\Tenancy
 * @author Mateen (Senior Software Engineer)
 */
abstract class TenantModel extends Model
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot the tenant model
     */
    protected static function booted(): void
    {
        parent::booted();

        // Auto-scope by cooperative_id for data isolation
        static::addGlobalScope('cooperative', function (Builder $builder) {
            $user = Auth::user();

            // Skip scoping if no authenticated user
            if (!$user) {
                return;
            }

            // Skip scoping for super admin
            if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                return;
            }

            // Get user's accessible cooperative IDs
            $cooperativeIds = [];
            if (method_exists($user, 'getAccessibleCooperativeIds')) {
                $cooperativeIds = $user->getAccessibleCooperativeIds();
            } elseif ($user->cooperatives) {
                $cooperativeIds = $user->cooperatives->pluck('id')->toArray();
            }

            // Apply cooperative scope
            if (!empty($cooperativeIds)) {
                $builder->whereIn('cooperative_id', $cooperativeIds);
            } else {
                // If user has no cooperative access, return empty result
                $builder->whereRaw('1 = 0');
            }
        });

        // Auto-set cooperative_id when creating
        static::creating(function ($model) {
            if (!isset($model->cooperative_id) && Auth::check()) {
                $user = Auth::user();

                // Skip auto-setting for super admin
                if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
                    return;
                }

                // Set to user's primary cooperative
                if (method_exists($user, 'primaryCooperative')) {
                    $primaryCooperative = $user->primaryCooperative();
                    if ($primaryCooperative) {
                        $model->cooperative_id = $primaryCooperative->id;
                    }
                }
            }

            // Set created_by if not set
            if (!isset($model->created_by) && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });

        // Set updated_by when updating
        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the cooperative this model belongs to
     */
    public function cooperative()
    {
        return $this->belongsTo(\App\Domain\Cooperative\Models\Cooperative::class);
    }

    /**
     * Get the user who created this record
     */
    public function creator()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated this record
     */
    public function updater()
    {
        return $this->belongsTo(\App\Domain\Auth\Models\User::class, 'updated_by');
    }

    /**
     * Scope to specific cooperative
     */
    public function scopeForCooperative(Builder $query, int $cooperativeId): Builder
    {
        return $query->where('cooperative_id', $cooperativeId);
    }

    /**
     * Scope without global cooperative scope
     */
    public function scopeWithoutCooperativeScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('cooperative');
    }

    /**
     * Scope for records created by specific user
     */
    public function scopeCreatedBy(Builder $query, int $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Check if current user can access this model
     */
    public function canAccess(): bool
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // Super admin can access everything
        if (method_exists($user, 'hasRole') && $user->hasRole('super_admin')) {
            return true;
        }

        // Check cooperative access
        if (method_exists($user, 'hasCooperativeAccess')) {
            return $user->hasCooperativeAccess($this->cooperative_id);
        }

        return false;
    }

    /**
     * Validate cooperative access before operations
     */
    public function validateCooperativeAccess(): void
    {
        if (!$this->canAccess()) {
            throw new \Exception('Access denied to this cooperative data');
        }
    }

    /**
     * Get model's display identifier
     */
    public function getDisplayIdentifier(): string
    {
        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->title)) {
            return $this->title;
        }

        return class_basename($this) . ' #' . $this->id;
    }
}
