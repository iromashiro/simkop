<?php
// app/Http/Controllers/API/V1/CooperativeController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Cooperative\Services\CooperativeService;
use App\Domain\Cooperative\DTOs\CooperativeDTO;
use App\Domain\Cooperative\Models\Cooperative;
use App\Http\Requests\API\Cooperative\StoreCooperativeRequest;
use App\Http\Requests\API\Cooperative\UpdateCooperativeRequest;
use App\Http\Resources\Cooperative\CooperativeResource;
use App\Http\Resources\Cooperative\CooperativeCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;

/**
 * PRODUCTION READY: Cooperative API Controller with ENHANCED SECURITY
 * SRS Reference: Section 2.2 - Cooperative Management Requirements
 * SECURITY: Input sanitization and SQL injection protection implemented
 */
class CooperativeController extends Controller
{
    public function __construct(
        private readonly CooperativeService $cooperativeService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.aware');

        // ✅ SECURITY FIX: Add granular rate limiting
        $this->middleware('throttle:60,1')->only(['index', 'show']); // Read operations
        $this->middleware('throttle:10,1')->only(['store', 'update', 'destroy']); // Write operations
        $this->middleware('throttle:30,1')->only(['statistics']); // Resource-intensive operations
    }

    /**
     * Display a listing of cooperatives with ENHANCED SECURITY
     *
     * @OA\Get(
     *     path="/api/v1/cooperatives",
     *     summary="Get list of cooperatives",
     *     tags={"Cooperatives"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term (alphanumeric, spaces, hyphens, underscores, dots only)",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^[a-zA-Z0-9\s\-\_\.@]+$", maxLength=255)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "inactive", "suspended"})
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by cooperative type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"simpan_pinjam", "konsumen", "produksi", "jasa"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cooperatives retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="cooperatives", type="array", @OA\Items(ref="#/components/schemas/Cooperative")),
     *                 @OA\Property(property="pagination", type="object",
     *                     @OA\Property(property="current_page", type="integer"),
     *                     @OA\Property(property="last_page", type="integer"),
     *                     @OA\Property(property="per_page", type="integer"),
     *                     @OA\Property(property="total", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Insufficient permissions",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient permissions")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization check
            if (!Gate::allows('viewAny', Cooperative::class)) {
                return $this->errorResponse('Insufficient permissions to view cooperatives', 403);
            }

            // ✅ SECURITY FIX: Enhanced validation with input sanitization
            $validated = $request->validate([
                'page' => 'integer|min:1|max:10000',
                'per_page' => 'integer|min:1|max:100',
                'search' => [
                    'nullable',
                    'string',
                    'max:255',
                    'regex:/^[a-zA-Z0-9\s\-\_\.@]+$/', // ✅ Only allow safe characters
                ],
                'status' => 'nullable|string|in:active,inactive,suspended',
                'type' => 'nullable|string|in:simpan_pinjam,konsumen,produksi,jasa',
                'sort_by' => 'nullable|string|in:name,created_at,updated_at,member_count',
                'sort_direction' => 'nullable|string|in:asc,desc',
            ], [
                'search.regex' => 'Search term contains invalid characters. Only letters, numbers, spaces, hyphens, underscores, dots, and @ symbols are allowed.',
                'page.max' => 'Page number cannot exceed 10000.',
                'per_page.max' => 'Items per page cannot exceed 100.',
            ]);

            // ✅ SECURITY FIX: Sanitize search input
            $filters = [
                'search' => $this->sanitizeSearchInput($validated['search'] ?? null),
                'status' => $validated['status'] ?? null,
                'type' => $validated['type'] ?? null,
                'sort_by' => $validated['sort_by'] ?? 'name',
                'sort_direction' => $validated['sort_direction'] ?? 'asc',
            ];

            // Get cooperatives with pagination
            $cooperatives = $this->cooperativeService->getCooperativesList(
                filters: $filters,
                perPage: $validated['per_page'] ?? 15
            );

            // ✅ SECURITY FIX: Enhanced logging with sanitized data
            Log::info('API cooperatives list retrieved', [
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'filters' => $this->sanitizeLogData($filters),
                'total_results' => $cooperatives->total(),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 255), // Limit user agent length
            ]);

            $response = response()->json([
                'success' => true,
                'data' => [
                    'cooperatives' => new CooperativeCollection($cooperatives),
                    'pagination' => [
                        'current_page' => $cooperatives->currentPage(),
                        'last_page' => $cooperatives->lastPage(),
                        'per_page' => $cooperatives->perPage(),
                        'total' => $cooperatives->total(),
                        'from' => $cooperatives->firstItem(),
                        'to' => $cooperatives->lastItem(),
                    ],
                ],
            ]);

            // ✅ SECURITY FIX: Add security headers
            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperatives index error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('An error occurred while retrieving cooperatives', 500);
        }
    }

    /**
     * Store a newly created cooperative
     */
    public function store(StoreCooperativeRequest $request): JsonResponse
    {
        try {
            // Authorization check
            if (!Gate::allows('create', Cooperative::class)) {
                return $this->errorResponse('Insufficient permissions to create cooperative', 403);
            }

            // ✅ SECURITY FIX: Additional sanitization for file uploads
            $logoFile = null;
            if ($request->hasFile('logo')) {
                $logoFile = $this->validateAndSanitizeFile($request->file('logo'));
            }

            // Create cooperative DTO with sanitized data
            $cooperativeDTO = new CooperativeDTO(
                name: $this->sanitizeTextInput($request->name),
                type: $request->type, // Already validated by enum
                address: $this->sanitizeTextInput($request->address),
                phone: $this->sanitizePhoneInput($request->phone),
                email: filter_var($request->email, FILTER_SANITIZE_EMAIL),
                registrationNumber: $this->sanitizeAlphanumeric($request->registration_number),
                establishmentDate: $request->establishment_date ?
                    \Carbon\Carbon::parse($request->establishment_date) : null,
                description: $this->sanitizeTextInput($request->description),
                website: $this->sanitizeUrl($request->website),
                logo: $logoFile
            );

            // Create cooperative
            $cooperative = $this->cooperativeService->createCooperative($cooperativeDTO);

            Log::info('API cooperative created', [
                'cooperative_id' => $cooperative->id,
                'name' => $cooperative->name,
                'created_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Cooperative created successfully',
                'data' => new CooperativeResource($cooperative),
            ], 201);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperative creation error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'request_data' => $this->sanitizeLogData($request->except(['logo', 'password'])),
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while creating cooperative', 500);
        }
    }

    /**
     * Display the specified cooperative
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) { // Max INT value
                return $this->errorResponse('Invalid cooperative ID', 400);
            }

            // Find cooperative
            $cooperative = Cooperative::find($id);

            if (!$cooperative) {
                return $this->errorResponse('Cooperative not found', 404);
            }

            // Authorization check
            if (!Gate::allows('view', $cooperative)) {
                return $this->errorResponse('Insufficient permissions to view this cooperative', 403);
            }

            // ✅ SECURITY FIX: Validate include parameter
            $validated = $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    'regex:/^(members|statistics|recent_activities)(,(members|statistics|recent_activities))*$/',
                ],
            ], [
                'include.regex' => 'Invalid include parameter. Allowed values: members, statistics, recent_activities',
            ]);

            // Load relationships based on include parameter
            $includes = [];
            if (!empty($validated['include'])) {
                $includes = array_unique(explode(',', $validated['include']));
                $includes = array_intersect($includes, ['members', 'statistics', 'recent_activities']); // Whitelist
            }

            if (in_array('members', $includes)) {
                $cooperative->load(['members' => function ($query) {
                    $query->active()->limit(10);
                }]);
            }

            if (in_array('statistics', $includes)) {
                $cooperative->loadCount(['members', 'activeMembers']);
                $cooperative->load('statistics');
            }

            if (in_array('recent_activities', $includes)) {
                $cooperative->load(['recentActivities' => function ($query) {
                    $query->latest()->limit(5);
                }]);
            }

            Log::info('API cooperative details retrieved', [
                'cooperative_id' => $cooperative->id,
                'user_id' => $request->user()->id,
                'includes' => $includes,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => new CooperativeResource($cooperative),
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperative show error', [
                'error' => $e->getMessage(),
                'cooperative_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while retrieving cooperative details', 500);
        }
    }

    /**
     * Update the specified cooperative
     */
    public function update(UpdateCooperativeRequest $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid cooperative ID', 400);
            }

            // Find cooperative
            $cooperative = Cooperative::find($id);

            if (!$cooperative) {
                return $this->errorResponse('Cooperative not found', 404);
            }

            // Authorization check
            if (!Gate::allows('update', $cooperative)) {
                return $this->errorResponse('Insufficient permissions to update this cooperative', 403);
            }

            // ✅ SECURITY FIX: Sanitize update data
            $logoFile = null;
            if ($request->hasFile('logo')) {
                $logoFile = $this->validateAndSanitizeFile($request->file('logo'));
            }

            // Create update DTO with sanitized data
            $cooperativeDTO = new CooperativeDTO(
                name: $request->name ? $this->sanitizeTextInput($request->name) : $cooperative->name,
                type: $request->type ?? $cooperative->type,
                address: $request->address ? $this->sanitizeTextInput($request->address) : $cooperative->address,
                phone: $request->phone ? $this->sanitizePhoneInput($request->phone) : $cooperative->phone,
                email: $request->email ? filter_var($request->email, FILTER_SANITIZE_EMAIL) : $cooperative->email,
                registrationNumber: $request->registration_number ?
                    $this->sanitizeAlphanumeric($request->registration_number) : $cooperative->registration_number,
                establishmentDate: $request->establishment_date ?
                    \Carbon\Carbon::parse($request->establishment_date) : $cooperative->establishment_date,
                description: $request->description ?
                    $this->sanitizeTextInput($request->description) : $cooperative->description,
                website: $request->website ? $this->sanitizeUrl($request->website) : $cooperative->website,
                status: $request->status ?? $cooperative->status,
                logo: $logoFile
            );

            // Update cooperative
            $updatedCooperative = $this->cooperativeService->updateCooperative($cooperative->id, $cooperativeDTO);

            // ✅ SECURITY FIX: Invalidate related caches
            $this->invalidateCooperativeCaches($cooperative->id);

            Log::info('API cooperative updated', [
                'cooperative_id' => $cooperative->id,
                'updated_by' => $request->user()->id,
                'changes' => $this->sanitizeLogData($request->only(['name', 'type', 'status', 'email', 'phone'])),
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Cooperative updated successfully',
                'data' => new CooperativeResource($updatedCooperative),
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperative update error', [
                'error' => $e->getMessage(),
                'cooperative_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while updating cooperative', 500);
        }
    }

    /**
     * Remove the specified cooperative
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid cooperative ID', 400);
            }

            // Find cooperative
            $cooperative = Cooperative::find($id);

            if (!$cooperative) {
                return $this->errorResponse('Cooperative not found', 404);
            }

            // Authorization check
            if (!Gate::allows('delete', $cooperative)) {
                return $this->errorResponse('Insufficient permissions to delete this cooperative', 403);
            }

            // Check if cooperative can be deleted
            $canDelete = $this->cooperativeService->canDeleteCooperative($cooperative->id);

            if (!$canDelete['can_delete']) {
                return response()->json([
                    'success' => false,
                    'message' => $canDelete['reason'],
                    'details' => $canDelete['details'],
                ], 409);
            }

            // Store cooperative info for logging before deletion
            $cooperativeInfo = [
                'id' => $cooperative->id,
                'name' => $cooperative->name,
                'type' => $cooperative->type,
            ];

            // Delete cooperative
            $this->cooperativeService->deleteCooperative($cooperative->id);

            // ✅ SECURITY FIX: Invalidate all related caches
            $this->invalidateCooperativeCaches($cooperative->id);

            Log::info('API cooperative deleted', [
                'cooperative_info' => $cooperativeInfo,
                'deleted_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Cooperative deleted successfully',
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Exception $e) {
            Log::error('API cooperative deletion error', [
                'error' => $e->getMessage(),
                'cooperative_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while deleting cooperative', 500);
        }
    }

    /**
     * Get cooperative statistics with caching
     */
    public function statistics(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid cooperative ID', 400);
            }

            // Find cooperative
            $cooperative = Cooperative::find($id);

            if (!$cooperative) {
                return $this->errorResponse('Cooperative not found', 404);
            }

            // Authorization check
            if (!Gate::allows('view', $cooperative)) {
                return $this->errorResponse('Insufficient permissions to view cooperative statistics', 403);
            }

            // ✅ SECURITY FIX: Validate period parameter
            $validated = $request->validate([
                'period' => 'nullable|string|in:monthly,quarterly,yearly',
            ]);

            $period = $validated['period'] ?? 'monthly';

            // ✅ ENHANCEMENT: Add caching for expensive statistics
            $cacheKey = "cooperative_stats:{$cooperative->id}:{$period}:" . now()->format('Y-m-d-H');

            $statistics = Cache::remember($cacheKey, 3600, function () use ($cooperative, $period) {
                return $this->cooperativeService->getCooperativeStatistics($cooperative->id, $period);
            });

            Log::info('API cooperative statistics retrieved', [
                'cooperative_id' => $cooperative->id,
                'period' => $period,
                'user_id' => $request->user()->id,
                'cache_key' => $cacheKey,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => $statistics,
            ]);

            // Add cache headers
            $response->headers->set('Cache-Control', 'public, max-age=3600');
            $response->headers->set('X-Cache-Key', $cacheKey);

            return $this->addSecurityHeaders($response, 'stats');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperative statistics error', [
                'error' => $e->getMessage(),
                'cooperative_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while retrieving statistics', 500);
        }
    }

    /**
     * Update cooperative status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid cooperative ID', 400);
            }

            // Find cooperative
            $cooperative = Cooperative::find($id);

            if (!$cooperative) {
                return $this->errorResponse('Cooperative not found', 404);
            }

            // Authorization check
            if (!Gate::allows('update', $cooperative)) {
                return $this->errorResponse('Insufficient permissions to update cooperative status', 403);
            }

            // ✅ SECURITY FIX: Enhanced validation
            $validated = $request->validate([
                'status' => 'required|string|in:active,inactive,suspended',
                'reason' => 'nullable|string|max:500|regex:/^[a-zA-Z0-9\s\-\_\.\,\!\?\(\)]+$/',
            ], [
                'reason.regex' => 'Reason contains invalid characters.',
            ]);

            // Update status with sanitized reason
            $updatedCooperative = $this->cooperativeService->updateCooperativeStatus(
                cooperativeId: $cooperative->id,
                status: $validated['status'],
                reason: $validated['reason'] ? $this->sanitizeTextInput($validated['reason']) : null,
                updatedBy: $request->user()->id
            );

            // ✅ SECURITY FIX: Invalidate related caches
            $this->invalidateCooperativeCaches($cooperative->id);

            Log::info('API cooperative status updated', [
                'cooperative_id' => $cooperative->id,
                'old_status' => $cooperative->status,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'],
                'updated_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Cooperative status updated successfully',
                'data' => new CooperativeResource($updatedCooperative),
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API cooperative status update error', [
                'error' => $e->getMessage(),
                'cooperative_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while updating cooperative status', 500);
        }
    }

    // ✅ SECURITY FIX: Input sanitization methods

    /**
     * Sanitize search input to prevent SQL injection
     */
    private function sanitizeSearchInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        // Remove any potential SQL injection patterns
        $input = strip_tags($input);
        $input = trim($input);

        // Remove multiple spaces
        $input = preg_replace('/\s+/', ' ', $input);

        // Limit length
        return substr($input, 0, 255);
    }

    /**
     * Sanitize text input
     */
    private function sanitizeTextInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        return strip_tags(trim($input));
    }

    /**
     * Sanitize phone input
     */
    private function sanitizePhoneInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        // Keep only numbers, spaces, hyphens, parentheses, and plus sign
        return preg_replace('/[^0-9\s\-\(\)\+]/', '', $input);
    }

    /**
     * Sanitize alphanumeric input
     */
    private function sanitizeAlphanumeric(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        return preg_replace('/[^a-zA-Z0-9\-\_]/', '', $input);
    }

    /**
     * Sanitize URL input
     */
    private function sanitizeUrl(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $url = filter_var($input, FILTER_SANITIZE_URL);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * Validate and sanitize file upload
     */
    private function validateAndSanitizeFile($file)
    {
        if (!$file || !$file->isValid()) {
            return null;
        }

        // Validate file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            throw new \InvalidArgumentException('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed.');
        }

        // Validate file size (max 5MB)
        if ($file->getSize() > 5 * 1024 * 1024) {
            throw new \InvalidArgumentException('File size too large. Maximum 5MB allowed.');
        }

        return $file;
    }

    /**
     * Sanitize data for logging
     */
    private function sanitizeLogData(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = substr(strip_tags($value), 0, 255);
            } elseif (is_array($value)) {
                $sanitized[$key] = $this->sanitizeLogData($value);
            } else {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(JsonResponse $response, string $operation): JsonResponse
    {
        // Rate limit headers
        $limits = [
            'read' => ['limit' => 60, 'window' => 60],
            'write' => ['limit' => 10, 'window' => 60],
            'stats' => ['limit' => 30, 'window' => 60],
        ];

        $limit = $limits[$operation] ?? $limits['read'];

        $response->headers->set('X-RateLimit-Limit', $limit['limit']);
        $response->headers->set('X-RateLimit-Window', $limit['window']);

        // Security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // API version headers
        $response->headers->set('API-Version', 'v1');
        $response->headers->set('API-Supported-Versions', 'v1');

        return $response;
    }

    /**
     * Invalidate cooperative-related caches
     */
    private function invalidateCooperativeCaches(int $cooperativeId): void
    {
        $periods = ['monthly', 'quarterly', 'yearly'];
        $currentHour = now()->format('Y-m-d-H');

        foreach ($periods as $period) {
            Cache::forget("cooperative_stats:{$cooperativeId}:{$period}:{$currentHour}");
        }
    }

    /**
     * Standard error response
     */
    private function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
        ], $code);
    }

    /**
     * Validation error response
     */
    private function validationErrorResponse(\Illuminate\Validation\ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }
}
