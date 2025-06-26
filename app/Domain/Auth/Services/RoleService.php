<?php
// app/Domain/Auth/Services/RoleService.php
namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Role;
use App\Domain\Auth\Models\Permission;
use App\Domain\Auth\DTOs\CreateRoleDTO;
use App\Domain\Auth\DTOs\UpdateRoleDTO;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RoleService
{
    /**
     * Get user permissions with caching
     */
    public function getUserPermissions(int $userId): array
    {
        $cacheKey = "user_permissions:{$userId}";

        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            $user = \App\Domain\User\Models\User::findOrFail($userId);
            $permissions = [];

            foreach ($user->roles as $role) {
                foreach ($role->permissions as $permission) {
                    $permissions[] = $permission->name;
                }
            }

            Log::debug('User permissions calculated and cached', [
                'user_id' => $userId,
                'permissions_count' => count($permissions),
            ]);

            return array_unique($permissions);
        });
    }

    /**
     * Check if user has specific permission with caching
     */
    public function userHasPermission(int $userId, string $permission): bool
    {
        $permissions = $this->getUserPermissions($userId);
        return in_array($permission, $permissions);
    }

    /**
     * Check multiple permissions at once
     */
    public function userHasPermissions(int $userId, array $permissions): array
    {
        $userPermissions = $this->getUserPermissions($userId);
        $result = [];

        foreach ($permissions as $permission) {
            $result[$permission] = in_array($permission, $userPermissions);
        }

        return $result;
    }

    /**
     * Check if user has any of the given permissions
     */
    public function userHasAnyPermission(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);

        foreach ($permissions as $permission) {
            if (in_array($permission, $userPermissions)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function userHasAllPermissions(int $userId, array $permissions): bool
    {
        $userPermissions = $this->getUserPermissions($userId);

        foreach ($permissions as $permission) {
            if (!in_array($permission, $userPermissions)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear user permission cache
     */
    private function clearUserPermissionCache(int $userId): void
    {
        $cacheKey = "user_permissions:{$userId}";
        Cache::forget($cacheKey);

        Log::debug('User permission cache cleared', ['user_id' => $userId]);
    }

    /**
     * Clear all users permission cache for a role
     */
    private function clearRoleUsersPermissionCache(int $roleId): void
    {
        $role = Role::with('users')->find($roleId);

        if ($role) {
            foreach ($role->users as $user) {
                $this->clearUserPermissionCache($user->id);
            }

            Log::debug('Role users permission cache cleared', [
                'role_id' => $roleId,
                'users_count' => $role->users->count(),
            ]);
        }
    }

    /**
     * Get user roles with caching
     */
    public function getUserRoles(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "user_roles:{$userId}";

        return Cache::remember($cacheKey, 1800, function () use ($userId) {
            $user = \App\Domain\User\Models\User::findOrFail($userId);
            return $user->roles()->with('permissions')->get();
        });
    }

    /**
     * Clear user roles cache
     */
    private function clearUserRolesCache(int $userId): void
    {
        $cacheKey = "user_roles:{$userId}";
        Cache::forget($cacheKey);

        Log::debug('User roles cache cleared', ['user_id' => $userId]);
    }

    public function createRole(CreateRoleDTO $dto): Role
    {
        return DB::transaction(function () use ($dto) {
            $role = Role::create([
                'cooperative_id' => $dto->cooperativeId,
                'name' => $dto->name,
                'display_name' => $dto->displayName,
                'description' => $dto->description,
                'is_system_role' => $dto->isSystemRole,
                'is_active' => $dto->isActive,
                'created_by' => $dto->createdBy,
            ]);

            if (!empty($dto->permissionIds)) {
                $role->syncPermissions($dto->permissionIds);
            }

            Log::info('Role created', [
                'role_id' => $role->id,
                'name' => $role->name,
                'cooperative_id' => $role->cooperative_id,
                'created_by' => $dto->createdBy,
            ]);

            return $role;
        });
    }

    public function updateRole(int $roleId, UpdateRoleDTO $dto): Role
    {
        return DB::transaction(function () use ($roleId, $dto) {
            $role = Role::findOrFail($roleId);

            $role->update([
                'display_name' => $dto->displayName ?? $role->display_name,
                'description' => $dto->description ?? $role->description,
                'is_active' => $dto->isActive ?? $role->is_active,
            ]);

            if ($dto->permissionIds !== null) {
                $role->syncPermissions($dto->permissionIds);

                // ✅ FIXED: Clear permission cache for all users with this role
                $this->clearRoleUsersPermissionCache($roleId);
            }

            Log::info('Role updated', [
                'role_id' => $role->id,
                'name' => $role->name,
                'cooperative_id' => $role->cooperative_id,
            ]);

            return $role;
        });
    }

    public function deleteRole(int $roleId): bool
    {
        return DB::transaction(function () use ($roleId) {
            $role = Role::findOrFail($roleId);

            if ($role->is_system_role) {
                throw new \Exception('Cannot delete system role');
            }

            if ($role->users()->count() > 0) {
                throw new \Exception('Cannot delete role that has assigned users');
            }

            // ✅ FIXED: Clear permission cache before deletion
            $this->clearRoleUsersPermissionCache($roleId);

            $role->permissions()->detach();
            $deleted = $role->delete();

            Log::info('Role deleted', [
                'role_id' => $roleId,
                'name' => $role->name,
                'cooperative_id' => $role->cooperative_id,
            ]);

            return $deleted;
        });
    }

    public function assignRoleToUser(int $userId, int $roleId): void
    {
        $user = \App\Domain\User\Models\User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        if ($user->cooperative_id !== $role->cooperative_id) {
            throw new \Exception('User and role must belong to the same cooperative');
        }

        if (!$user->roles()->where('role_id', $roleId)->exists()) {
            $user->roles()->attach($roleId);

            // ✅ FIXED: Clear permission and roles cache
            $this->clearUserPermissionCache($userId);
            $this->clearUserRolesCache($userId);

            Log::info('Role assigned to user', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'cooperative_id' => $user->cooperative_id,
            ]);
        }
    }

    public function removeRoleFromUser(int $userId, int $roleId): void
    {
        $user = \App\Domain\User\Models\User::findOrFail($userId);
        $user->roles()->detach($roleId);

        // ✅ FIXED: Clear permission and roles cache
        $this->clearUserPermissionCache($userId);
        $this->clearUserRolesCache($userId);

        Log::info('Role removed from user', [
            'user_id' => $userId,
            'role_id' => $roleId,
            'cooperative_id' => $user->cooperative_id,
        ]);
    }

    /**
     * Sync user roles and clear cache
     */
    public function syncUserRoles(int $userId, array $roleIds): void
    {
        $user = \App\Domain\User\Models\User::findOrFail($userId);

        // Validate all roles belong to same cooperative
        $roles = Role::whereIn('id', $roleIds)->get();
        foreach ($roles as $role) {
            if ($role->cooperative_id !== $user->cooperative_id) {
                throw new \Exception('All roles must belong to the same cooperative as the user');
            }
        }

        $user->roles()->sync($roleIds);

        // ✅ FIXED: Clear permission and roles cache
        $this->clearUserPermissionCache($userId);
        $this->clearUserRolesCache($userId);

        Log::info('User roles synchronized', [
            'user_id' => $userId,
            'role_ids' => $roleIds,
            'cooperative_id' => $user->cooperative_id,
        ]);
    }

    public function getCooperativeRoles(int $cooperativeId): \Illuminate\Database\Eloquent\Collection
    {
        $cacheKey = "cooperative_roles:{$cooperativeId}";

        return Cache::remember($cacheKey, 3600, function () use ($cooperativeId) {
            return Role::where('cooperative_id', $cooperativeId)
                ->with(['permissions', 'users'])
                ->orderBy('name')
                ->get();
        });
    }

    /**
     * Clear cooperative roles cache
     */
    private function clearCooperativeRolesCache(int $cooperativeId): void
    {
        $cacheKey = "cooperative_roles:{$cooperativeId}";
        Cache::forget($cacheKey);

        Log::debug('Cooperative roles cache cleared', ['cooperative_id' => $cooperativeId]);
    }

    public function createDefaultRoles(int $cooperativeId, int $createdBy): array
    {
        $defaultRoles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Administrator',
                'description' => 'Full system access',
                'permissions' => Permission::all()->pluck('id')->toArray(),
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Administrative access',
                'permissions' => Permission::whereIn('group', ['cooperative', 'member', 'financial'])->pluck('id')->toArray(),
            ],
            [
                'name' => 'manager',
                'display_name' => 'Manager',
                'description' => 'Management access',
                'permissions' => Permission::whereIn('group', ['member', 'financial'])->pluck('id')->toArray(),
            ],
            [
                'name' => 'staff',
                'display_name' => 'Staff',
                'description' => 'Basic staff access',
                'permissions' => Permission::where('group', 'member')->pluck('id')->toArray(),
            ],
        ];

        $createdRoles = [];

        foreach ($defaultRoles as $roleData) {
            $dto = new CreateRoleDTO(
                cooperativeId: $cooperativeId,
                name: $roleData['name'],
                displayName: $roleData['display_name'],
                description: $roleData['description'],
                permissionIds: $roleData['permissions'],
                isSystemRole: true,
                isActive: true,
                createdBy: $createdBy
            );

            $createdRoles[] = $this->createRole($dto);
        }

        // Clear cooperative roles cache after creating default roles
        $this->clearCooperativeRolesCache($cooperativeId);

        return $createdRoles;
    }

    /**
     * Get permission statistics for cooperative
     */
    public function getPermissionStatistics(int $cooperativeId): array
    {
        $cacheKey = "permission_stats:{$cooperativeId}";

        return Cache::remember($cacheKey, 3600, function () use ($cooperativeId) {
            $roles = Role::where('cooperative_id', $cooperativeId)
                ->with(['permissions', 'users'])
                ->get();

            $stats = [
                'total_roles' => $roles->count(),
                'active_roles' => $roles->where('is_active', true)->count(),
                'system_roles' => $roles->where('is_system_role', true)->count(),
                'custom_roles' => $roles->where('is_system_role', false)->count(),
                'total_users_with_roles' => $roles->sum(fn($role) => $role->users->count()),
                'permissions_by_group' => [],
                'most_used_permissions' => [],
            ];

            // Group permissions by category
            $allPermissions = Permission::all()->groupBy('group');
            foreach ($allPermissions as $group => $permissions) {
                $stats['permissions_by_group'][$group] = $permissions->count();
            }

            // Find most used permissions
            $permissionUsage = [];
            foreach ($roles as $role) {
                foreach ($role->permissions as $permission) {
                    $permissionUsage[$permission->name] = ($permissionUsage[$permission->name] ?? 0) + $role->users->count();
                }
            }

            arsort($permissionUsage);
            $stats['most_used_permissions'] = array_slice($permissionUsage, 0, 10, true);

            return $stats;
        });
    }

    /**
     * Clear all permission-related caches for cooperative
     */
    public function clearAllPermissionCaches(int $cooperativeId): void
    {
        // Clear cooperative roles cache
        $this->clearCooperativeRolesCache($cooperativeId);

        // Clear permission statistics cache
        $cacheKey = "permission_stats:{$cooperativeId}";
        Cache::forget($cacheKey);

        // Clear all user permission caches for this cooperative
        $users = \App\Domain\User\Models\User::where('cooperative_id', $cooperativeId)->get();
        foreach ($users as $user) {
            $this->clearUserPermissionCache($user->id);
            $this->clearUserRolesCache($user->id);
        }

        Log::info('All permission caches cleared for cooperative', [
            'cooperative_id' => $cooperativeId,
            'users_affected' => $users->count(),
        ]);
    }
}
