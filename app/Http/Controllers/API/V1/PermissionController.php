<?php
// app/Http/Controllers/API/V1/PermissionController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Services\RoleService;
use App\Domain\Auth\Models\Permission;
use App\Http\Resources\API\V1\PermissionResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * @group Permissions
 *
 * APIs for permission management
 */
class PermissionController extends Controller
{
    public function __construct(
        private readonly RoleService $roleService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.scope');
    }

    /**
     * Get all permissions
     *
     * @authenticated
     * @queryParam group string Filter by permission group. Example: financial
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'group' => 'string|max:50',
        ]);

        $query = Permission::orderBy('group')->orderBy('name');

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $permissions = $query->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
        ]);
    }

    /**
     * Get permissions grouped by category
     *
     * @authenticated
     */
    public function grouped(): JsonResponse
    {
        $permissions = Permission::orderBy('group')->orderBy('name')->get();

        $grouped = $permissions->groupBy('group')->map(function ($groupPermissions) {
            return PermissionResource::collection($groupPermissions);
        });

        return response()->json([
            'success' => true,
            'data' => $grouped,
        ]);
    }

    /**
     * Check user permissions
     *
     * @authenticated
     * @bodyParam permissions array required Array of permission names to check
     */
    public function checkUserPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|max:100',
        ]);

        $user = Auth::user();

        $results = $this->roleService->userHasPermissions($user->id, $request->permissions);

        return response()->json([
            'success' => true,
            'data' => $results,
        ]);
    }

    /**
     * Get user permissions
     *
     * @authenticated
     * @queryParam user_id integer Target user ID (defaults to current user)
     */
    public function userPermissions(Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'integer|exists:users,id',
        ]);

        $user = Auth::user();
        $targetUserId = $request->get('user_id', $user->id);

        // Check if user can view other user's permissions
        if ($targetUserId !== $user->id && !$this->roleService->userHasPermission($user->id, 'view_user_permissions')) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient permissions to view user permissions',
            ], 403);
        }

        $permissions = $this->roleService->getUserPermissions($targetUserId);

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Get permission groups
     *
     * @authenticated
     */
    public function groups(): JsonResponse
    {
        $groups = Permission::distinct('group')
            ->orderBy('group')
            ->pluck('group')
            ->map(function ($group) {
                return [
                    'name' => $group,
                    'display_name' => ucwords(str_replace('_', ' ', $group)),
                    'count' => Permission::where('group', $group)->count(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Search permissions
     *
     * @authenticated
     * @queryParam q string Search query
     * @queryParam group string Filter by group
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'string|min:2|max:100',
            'group' => 'string|max:50',
        ]);

        $query = Permission::query();

        if ($request->has('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('display_name', 'ILIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'ILIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('group')) {
            $query->where('group', $request->group);
        }

        $permissions = $query->orderBy('group')->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data' => PermissionResource::collection($permissions),
            'meta' => [
                'total' => $permissions->count(),
                'search_query' => $request->q,
                'group_filter' => $request->group,
            ],
        ]);
    }
}
