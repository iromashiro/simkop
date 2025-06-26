<?php
// app/Http/Controllers/API/V1/RoleController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Services\RoleService;
use App\Domain\Auth\DTOs\CreateRoleDTO;
use App\Domain\Auth\DTOs\UpdateRoleDTO;
use App\Domain\Auth\Models\Role;
use App\Http\Requests\API\V1\Role\CreateRoleRequest;
use App\Http\Requests\API\V1\Role\UpdateRoleRequest;
use App\Http\Requests\API\V1\Role\AssignRoleRequest;
use App\Http\Resources\API\V1\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Roles & Permissions
 *
 * APIs for role and permission management
 */
class RoleController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_roles')->except(['index', 'show', 'userRoles']);
    }

    /**
     * Get cooperative roles
     *
     * @authenticated
     * @queryParam include_permissions boolean Include role permissions. Example: true
     * @queryParam include_users boolean Include role users. Example: true
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'include_permissions' => 'boolean',
            'include_users' => 'boolean',
        ]);

        $user = Auth::user();

        $query = Role::where('cooperative_id', $user->cooperative_id)
            ->orderBy('name');

        if ($request->boolean('include_permissions')) {
            $query->with('permissions');
        }

        if ($request->boolean('include_users')) {
            $query->with('users');
        }

        $roles = $query->get();

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Create new role
     *
     * @authenticated
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateRoleDTO(
                cooperativeId: $user->cooperative_id,
                name: $request->name,
                displayName: $request->display_name,
                description: $request->description,
                permissionIds: $request->permission_ids ?? [],
                isSystemRole: false,
                isActive: $request->is_active ?? true,
                createdBy: $user->id
            );

            $role = $this->roleService->createRole($dto);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => new RoleResource($role->load('permissions')),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get role details
     *
     * @authenticated
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $role = Role::where('cooperative_id', $user->cooperative_id)
            ->with(['permissions', 'users'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Update role
     *
     * @authenticated
     */
    public function update(int $id, UpdateRoleRequest $request): JsonResponse
    {
        try {
            $dto = new UpdateRoleDTO(
                displayName: $request->display_name,
                description: $request->description,
                permissionIds: $request->permission_ids,
                isActive: $request->is_active
            );

            $role = $this->roleService->updateRole($id, $dto);

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => new RoleResource($role->load('permissions')),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete role
     *
     * @authenticated
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $success = $this->roleService->deleteRole($id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign role to user
     *
     * @authenticated
     */
    public function assignToUser(AssignRoleRequest $request): JsonResponse
    {
        try {
            $this->roleService->assignRoleToUser($request->user_id, $request->role_id);

            return response()->json([
                'success' => true,
                'message' => 'Role assigned successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove role from user
     *
     * @authenticated
     */
    public function removeFromUser(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_id' => 'required|integer|exists:roles,id',
        ]);

        try {
            $this->roleService->removeRoleFromUser($request->user_id, $request->role_id);

            return response()->json([
                'success' => true,
                'message' => 'Role removed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove role',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync user roles
     *
     * @authenticated
     */
    public function syncUserRoles(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role_ids' => 'required|array',
            'role_ids.*' => 'integer|exists:roles,id',
        ]);

        try {
            $this->roleService->syncUserRoles($request->user_id, $request->role_ids);

            return response()->json([
                'success' => true,
                'message' => 'User roles synchronized successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync user roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user roles
     *
     * @authenticated
     */
    public function userRoles(int $userId): JsonResponse
    {
        $user = Auth::user();

        $roles = $this->roleService->getUserRoles($userId);

        return response()->json([
            'success' => true,
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Get permission statistics
     *
     * @authenticated
     */
    public function statistics(): JsonResponse
    {
        $user = Auth::user();

        $stats = $this->roleService->getPermissionStatistics($user->cooperative_id);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Create default roles for cooperative
     *
     * @authenticated
     */
    public function createDefaults(): JsonResponse
    {
        $user = Auth::user();

        try {
            $roles = $this->roleService->createDefaultRoles($user->cooperative_id, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'Default roles created successfully',
                'data' => RoleResource::collection($roles),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create default roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
