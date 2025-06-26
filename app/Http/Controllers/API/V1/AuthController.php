<?php
// app/Http/Controllers/API/V1/AuthController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\User\Services\AuthenticationService;
use App\Domain\User\DTOs\LoginDTO;
use App\Domain\User\DTOs\RegisterDTO;
use App\Http\Requests\API\LoginRequest;
use App\Http\Requests\API\RegisterRequest;
use App\Http\Resources\User\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * PRODUCTION READY: API Authentication Controller with comprehensive security
 * SRS Reference: Section 2.1 - Authentication and Authorization Requirements
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService
    ) {}

    /**
     * User login via API
     *
     * @OA\Post(
     *     path="/api/v1/auth/login",
     *     summary="User login",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email","password"},
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="cooperative_id", type="integer", nullable=true),
     *             @OA\Property(property="remember", type="boolean", default=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_at", type="string", format="datetime")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid credentials")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            Log::info('API login attempt', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            $loginDTO = new LoginDTO(
                email: $request->email,
                password: $request->password,
                cooperativeId: $request->cooperative_id,
                remember: $request->boolean('remember', false),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $result = $this->authService->authenticateForApi($loginDTO);

            if (!$result->success) {
                Log::warning('API login failed', [
                    'email' => $request->email,
                    'reason' => $result->message,
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result->message,
                ], 401);
            }

            // Create API token
            $token = $result->user->createToken(
                name: 'API Token',
                abilities: $this->getUserAbilities($result->user),
                expiresAt: now()->addDays(30)
            );

            Log::info('API login successful', [
                'user_id' => $result->user->id,
                'cooperative_id' => $result->user->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => new UserResource($result->user),
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                    'abilities' => $token->accessToken->abilities,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API login error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during login',
            ], 500);
        }
    }

    /**
     * User registration via API
     *
     * @OA\Post(
     *     path="/api/v1/auth/register",
     *     summary="User registration",
     *     tags={"Authentication"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation","cooperative_id"},
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="email", type="string", format="email"),
     *             @OA\Property(property="password", type="string", format="password"),
     *             @OA\Property(property="password_confirmation", type="string", format="password"),
     *             @OA\Property(property="cooperative_id", type="integer"),
     *             @OA\Property(property="phone", type="string", nullable=true),
     *             @OA\Property(property="role", type="string", enum={"member", "staff"}, default="member")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Registration successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Registration successful"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", ref="#/components/schemas/User"),
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer")
     *             )
     *         )
     *     )
     * )
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        try {
            Log::info('API registration attempt', [
                'email' => $request->email,
                'cooperative_id' => $request->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            $registerDTO = new RegisterDTO(
                name: $request->name,
                email: $request->email,
                password: $request->password,
                cooperativeId: $request->cooperative_id,
                phone: $request->phone,
                role: $request->get('role', 'member')
            );

            $result = $this->authService->registerUser($registerDTO);

            if (!$result->success) {
                return response()->json([
                    'success' => false,
                    'message' => $result->message,
                ], 400);
            }

            // Create API token for new user
            $token = $result->user->createToken(
                name: 'API Token',
                abilities: $this->getUserAbilities($result->user),
                expiresAt: now()->addDays(30)
            );

            Log::info('API registration successful', [
                'user_id' => $result->user->id,
                'cooperative_id' => $result->user->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Registration successful',
                'data' => [
                    'user' => new UserResource($result->user),
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                ],
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('API registration error', [
                'error' => $e->getMessage(),
                'email' => $request->email,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during registration',
            ], 500);
        }
    }

    /**
     * Get authenticated user profile
     *
     * @OA\Get(
     *     path="/api/v1/auth/me",
     *     summary="Get authenticated user profile",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/User")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     )
     * )
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => new UserResource($user->load(['cooperative', 'roles', 'permissions'])),
            ]);
        } catch (\Exception $e) {
            Log::error('API me endpoint error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while retrieving user profile',
            ], 500);
        }
    }

    /**
     * Refresh authentication token
     *
     * @OA\Post(
     *     path="/api/v1/auth/refresh",
     *     summary="Refresh authentication token",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="token", type="string"),
     *                 @OA\Property(property="token_type", type="string", example="Bearer"),
     *                 @OA\Property(property="expires_at", type="string", format="datetime")
     *             )
     *         )
     *     )
     * )
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            // Create new token
            $token = $user->createToken(
                name: 'API Token (Refreshed)',
                abilities: $this->getUserAbilities($user),
                expiresAt: now()->addDays(30)
            );

            Log::info('API token refreshed', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token->plainTextToken,
                    'token_type' => 'Bearer',
                    'expires_at' => $token->accessToken->expires_at,
                    'abilities' => $token->accessToken->abilities,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('API token refresh error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while refreshing token',
            ], 500);
        }
    }

    /**
     * User logout
     *
     * @OA\Post(
     *     path="/api/v1/auth/logout",
     *     summary="User logout",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Logout successful")
     *         )
     *     )
     * )
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke current token
            $request->user()->currentAccessToken()->delete();

            Log::info('API logout successful', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Logout successful',
            ]);
        } catch (\Exception $e) {
            Log::error('API logout error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred during logout',
            ], 500);
        }
    }

    /**
     * Revoke all user tokens
     *
     * @OA\Post(
     *     path="/api/v1/auth/logout-all",
     *     summary="Logout from all devices",
     *     tags={"Authentication"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="All tokens revoked successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="All tokens revoked successfully")
     *         )
     *     )
     * )
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Revoke all tokens for this user
            $user->tokens()->delete();

            Log::info('API logout all successful', [
                'user_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All tokens revoked successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('API logout all error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while revoking tokens',
            ], 500);
        }
    }

    /**
     * Get user abilities based on roles and permissions
     */
    private function getUserAbilities($user): array
    {
        $abilities = ['read'];

        // Add abilities based on user roles
        if ($user->hasRole('super_admin')) {
            $abilities = ['*']; // All abilities
        } elseif ($user->hasRole('cooperative_admin')) {
            $abilities = [
                'read',
                'create',
                'update',
                'delete',
                'manage-members',
                'manage-finances',
                'generate-reports',
                'manage-shu',
                'manage-budgets'
            ];
        } elseif ($user->hasRole('staff')) {
            $abilities = [
                'read',
                'create',
                'update',
                'manage-members',
                'manage-finances',
                'generate-reports'
            ];
        } elseif ($user->hasRole('member')) {
            $abilities = [
                'read',
                'view-own-data',
                'update-own-profile'
            ];
        }

        return $abilities;
    }
}
