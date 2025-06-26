<?php
// app/Domain/Auth/Models/Permission.php
namespace App\Domain\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'group',
        'is_system_permission',
    ];

    protected $casts = [
        'is_system_permission' => 'boolean',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withTimestamps();
    }

    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopeSystemPermissions($query)
    {
        return $query->where('is_system_permission', true);
    }

    public function scopeCustomPermissions($query)
    {
        return $query->where('is_system_permission', false);
    }

    public static function getGroupedPermissions(): array
    {
        return self::all()->groupBy('group')->toArray();
    }
}
