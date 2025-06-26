<?php
// app/Http/Controllers/Web/UserController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Auth\Services\UserService;
use App\Domain\Auth\Services\RoleService;
use App\Domain\Auth\Models\User;
use App\Domain\Auth\DTOs\CreateUserDTO;
use App\Domain\Auth\DTOs\UpdateUserDTO;
use App\Http\Requests\Web\User\CreateUserRequest;
use App\Http\Requests\Web\User\UpdateUserRequest;
use App\Http\Requests\Web\User\UpdateProfileRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly RoleService $roleService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_users')->except(['show', 'profile', 'updateProfile']);
    }

    /**
     * Display user list
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = User::where('cooperative_id', $user->cooperative_id)
            ->with(['roles', 'cooperative'])
            ->orderBy('name');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('email', 'ILIKE', "%{$search}%")
                    ->orWhere('phone', 'ILIKE', "%{$search}%");
            });
        }

        // Role filter
        if ($request->filled('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query->paginate(20)->withQueryString();

        // Get available roles for filter
        $roles = \App\Domain\Auth\Models\Role::where('cooperative_id', $user->cooperative_id)
            ->orderBy('display_name')
            ->get();

        return view('users.index', compact('users', 'roles'));
    }

    /**
     * Show create user form
     */
    public function create(): View
    {
        $user = Auth::user();

        $roles = \App\Domain\Auth\Models\Role::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('users.create', compact('roles'));
    }

    /**
     * Store new user
     */
    public function store(CreateUserRequest $request): RedirectResponse
    {
        try {
            $currentUser = Auth::user();

            $dto = new CreateUserDTO(
                cooperativeId: $currentUser->cooperative_id,
                name: $request->name,
                email: $request->email,
                phone: $request->phone,
                password: $request->password,
                roleIds: $request->role_ids ?? [],
                isActive: $request->boolean('is_active', true),
                emailVerifiedAt: $request->boolean('email_verified') ? now() : null,
                createdBy: $currentUser->id
            );

            $user = $this->userService->createUser($dto);

            return redirect()
                ->route('users.show', $user)
                ->with('success', 'User created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create user: ' . $e->getMessage()]);
        }
    }

    /**
     * Display user details
     */
    public function show(User $user): View
    {
        $currentUser = Auth::user();

        // Ensure user belongs to same cooperative
        if ($user->cooperative_id !== $currentUser->cooperative_id) {
            abort(404);
        }

        $user->load(['roles.permissions', 'cooperative', 'createdBy']);

        // Get user statistics
        $statistics = $this->userService->getUserStatistics($user->id);

        // Get recent activities
        $recentActivities = \App\Domain\System\Models\ActivityLog::where('user_id', $user->id)
            ->where('cooperative_id', $user->cooperative_id)
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('users.show', compact('user', 'statistics', 'recentActivities'));
    }

    /**
     * Show edit user form
     */
    public function edit(User $user): View
    {
        $currentUser = Auth::user();

        // Ensure user belongs to same cooperative
        if ($user->cooperative_id !== $currentUser->cooperative_id) {
            abort(404);
        }

        $user->load('roles');

        $roles = \App\Domain\Auth\Models\Role::where('cooperative_id', $currentUser->cooperative_id)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('users.edit', compact('user', 'roles'));
    }

    /**
     * Update user
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        try {
            $currentUser = Auth::user();

            // Ensure user belongs to same cooperative
            if ($user->cooperative_id !== $currentUser->cooperative_id) {
                abort(404);
            }

            $dto = new UpdateUserDTO(
                name: $request->name,
                email: $request->email,
                phone: $request->phone,
                password: $request->filled('password') ? $request->password : null,
                roleIds: $request->role_ids,
                isActive: $request->boolean('is_active'),
                emailVerifiedAt: $request->boolean('email_verified') ?
                    ($user->email_verified_at ?? now()) : null
            );

            $this->userService->updateUser($user->id, $dto);

            return redirect()
                ->route('users.show', $user)
                ->with('success', 'User updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update user: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete user
     */
    public function destroy(User $user): RedirectResponse
    {
        try {
            $currentUser = Auth::user();

            // Ensure user belongs to same cooperative
            if ($user->cooperative_id !== $currentUser->cooperative_id) {
                abort(404);
            }

            // Prevent self-deletion
            if ($user->id === $currentUser->id) {
                return back()->withErrors(['error' => 'You cannot delete your own account']);
            }

            $this->userService->deleteUser($user->id);

            return redirect()
                ->route('users.index')
                ->with('success', 'User deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete user: ' . $e->getMessage()]);
        }
    }

    /**
     * Show user profile
     */
    public function profile(): View
    {
        $user = Auth::user();
        $user->load(['roles.permissions', 'cooperative']);

        // Get user statistics
        $statistics = $this->userService->getUserStatistics($user->id);

        return view('users.profile', compact('user', 'statistics'));
    }

    /**
     * Update user profile
     */
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $updateData = [
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
            ];

            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            return back()->with('success', 'Profile updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update profile: ' . $e->getMessage()]);
        }
    }

    /**
     * Toggle user status
     */
    public function toggleStatus(User $user): RedirectResponse
    {
        try {
            $currentUser = Auth::user();

            // Ensure user belongs to same cooperative
            if ($user->cooperative_id !== $currentUser->cooperative_id) {
                abort(404);
            }

            // Prevent self-deactivation
            if ($user->id === $currentUser->id) {
                return back()->withErrors(['error' => 'You cannot deactivate your own account']);
            }

            $user->update(['is_active' => !$user->is_active]);

            $status = $user->is_active ? 'activated' : 'deactivated';

            return back()->with('success', "User {$status} successfully");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to toggle user status: ' . $e->getMessage()]);
        }
    }

    /**
     * Reset user password
     */
    public function resetPassword(User $user): RedirectResponse
    {
        try {
            $currentUser = Auth::user();

            // Ensure user belongs to same cooperative
            if ($user->cooperative_id !== $currentUser->cooperative_id) {
                abort(404);
            }

            $newPassword = $this->userService->resetUserPassword($user->id);

            return back()->with('success', "Password reset successfully. New password: {$newPassword}");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to reset password: ' . $e->getMessage()]);
        }
    }

    /**
     * Export users
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();

        return $this->userService->exportUsers($user->cooperative_id, $request->all());
    }
}
