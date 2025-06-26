<?php
// app/Http/Controllers/API/V1/MemberController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Member\Services\MemberService;
use App\Domain\Member\DTOs\MemberDTO;
use App\Domain\Member\Models\Member;
use App\Http\Requests\API\Member\StoreMemberRequest;
use App\Http\Requests\API\Member\UpdateMemberRequest;
use App\Http\Resources\Member\MemberResource;
use App\Http\Resources\Member\MemberCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;

/**
 * PRODUCTION READY: Member API Controller with ENHANCED SECURITY
 * SRS Reference: Section 2.3 - Member Management Requirements
 * SECURITY: Input sanitization and SQL injection protection implemented
 */
class MemberController extends Controller
{
    public function __construct(
        private readonly MemberService $memberService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.aware');

        // ✅ SECURITY FIX: Add granular rate limiting
        $this->middleware('throttle:60,1')->only(['index', 'show', 'financialSummary']); // Read operations
        $this->middleware('throttle:10,1')->only(['store', 'update', 'destroy']); // Write operations
        $this->middleware('throttle:20,1')->only(['updateStatus']); // Status operations
    }

    /**
     * Display a listing of members with ENHANCED SECURITY
     *
     * @OA\Get(
     *     path="/api/v1/members",
     *     summary="Get list of members",
     *     tags={"Members"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by name, member number, or email (alphanumeric and safe characters only)",
     *         required=false,
     *         @OA\Schema(type="string", pattern="^[a-zA-Z0-9\s\-\_\.@]+$", maxLength=255)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Members retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Authorization check
            if (!Gate::allows('viewAny', Member::class)) {
                return $this->errorResponse('Insufficient permissions to view members', 403);
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
                'membership_type' => 'nullable|string|in:regular,premium,honorary',
                'sort_by' => 'nullable|string|in:name,member_number,join_date,total_savings',
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
                'membership_type' => $validated['membership_type'] ?? null,
                'sort_by' => $validated['sort_by'] ?? 'name',
                'sort_direction' => $validated['sort_direction'] ?? 'asc',
            ];

            // Get current user's cooperative context
            $cooperativeId = $request->user()->cooperative_id;

            // ✅ SECURITY FIX: Validate cooperative context
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Get members with pagination
            $members = $this->memberService->getMembersList(
                cooperativeId: $cooperativeId,
                filters: $filters,
                perPage: $validated['per_page'] ?? 15
            );

            // ✅ SECURITY FIX: Enhanced logging with sanitized data
            Log::info('API members list retrieved', [
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'filters' => $this->sanitizeLogData($filters),
                'total_results' => $members->total(),
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 255),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => [
                    'members' => new MemberCollection($members),
                    'pagination' => [
                        'current_page' => $members->currentPage(),
                        'last_page' => $members->lastPage(),
                        'per_page' => $members->perPage(),
                        'total' => $members->total(),
                        'from' => $members->firstItem(),
                        'to' => $members->lastItem(),
                    ],
                ],
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API members index error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->errorResponse('An error occurred while retrieving members', 500);
        }
    }

    /**
     * Store a newly created member
     */
    public function store(StoreMemberRequest $request): JsonResponse
    {
        try {
            // Authorization check
            if (!Gate::allows('create', Member::class)) {
                return $this->errorResponse('Insufficient permissions to create member', 403);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // ✅ SECURITY FIX: Additional validation for financial data
            $initialDeposit = $request->get('initial_deposit', 0);
            if ($initialDeposit < 0 || $initialDeposit > 1000000000) { // Max 1 billion
                return $this->errorResponse('Invalid initial deposit amount', 400);
            }

            // Create member DTO with sanitized data
            $memberDTO = new MemberDTO(
                cooperativeId: $cooperativeId,
                name: $this->sanitizeTextInput($request->name),
                email: filter_var($request->email, FILTER_SANITIZE_EMAIL),
                phone: $this->sanitizePhoneInput($request->phone),
                address: $this->sanitizeTextInput($request->address),
                idNumber: $this->sanitizeAlphanumeric($request->id_number),
                birthDate: $request->birth_date ? \Carbon\Carbon::parse($request->birth_date) : null,
                gender: $request->gender, // Already validated by enum
                occupation: $this->sanitizeTextInput($request->occupation),
                membershipType: $request->get('membership_type', 'regular'),
                initialDeposit: $initialDeposit
            );

            // Create member
            $member = $this->memberService->createMember($memberDTO);

            Log::info('API member created', [
                'member_id' => $member->id,
                'member_number' => $member->member_number,
                'name' => $member->name,
                'cooperative_id' => $member->cooperative_id,
                'created_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Member created successfully',
                'data' => new MemberResource($member),
            ], 201);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API member creation error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'request_data' => $this->sanitizeLogData($request->except(['initial_deposit', 'id_number'])),
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while creating member', 500);
        }
    }

    /**
     * Display the specified member
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid member ID', 400);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Find member with tenant isolation
            $member = Member::where('cooperative_id', $cooperativeId)->find($id);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            // Authorization check
            if (!Gate::allows('view', $member)) {
                return $this->errorResponse('Insufficient permissions to view this member', 403);
            }

            // ✅ SECURITY FIX: Validate include parameter
            $validated = $request->validate([
                'include' => [
                    'nullable',
                    'string',
                    'regex:/^(savings|loans|transactions|statistics)(,(savings|loans|transactions|statistics))*$/',
                ],
            ], [
                'include.regex' => 'Invalid include parameter. Allowed values: savings, loans, transactions, statistics',
            ]);

            // Load relationships based on include parameter
            $includes = [];
            if (!empty($validated['include'])) {
                $includes = array_unique(explode(',', $validated['include']));
                $includes = array_intersect($includes, ['savings', 'loans', 'transactions', 'statistics']); // Whitelist
            }

            if (in_array('savings', $includes)) {
                $member->load(['savings' => function ($query) {
                    $query->latest()->limit(10);
                }]);
            }

            if (in_array('loans', $includes)) {
                $member->load(['loans' => function ($query) {
                    $query->with('payments')->latest();
                }]);
            }

            if (in_array('transactions', $includes)) {
                $member->load(['recentTransactions' => function ($query) {
                    $query->latest()->limit(20);
                }]);
            }

            if (in_array('statistics', $includes)) {
                $member->loadCount(['savings', 'loans']);
                $member->load('statistics');
            }

            Log::info('API member details retrieved', [
                'member_id' => $member->id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'includes' => $includes,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => new MemberResource($member),
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API member show error', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while retrieving member details', 500);
        }
    }

    /**
     * Update the specified member
     */
    public function update(UpdateMemberRequest $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid member ID', 400);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Find member with tenant isolation
            $member = Member::where('cooperative_id', $cooperativeId)->find($id);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            // Authorization check
            if (!Gate::allows('update', $member)) {
                return $this->errorResponse('Insufficient permissions to update this member', 403);
            }

            // Create update DTO with sanitized data
            $memberDTO = new MemberDTO(
                cooperativeId: $member->cooperative_id,
                name: $request->name ? $this->sanitizeTextInput($request->name) : $member->name,
                email: $request->email ? filter_var($request->email, FILTER_SANITIZE_EMAIL) : $member->email,
                phone: $request->phone ? $this->sanitizePhoneInput($request->phone) : $member->phone,
                address: $request->address ? $this->sanitizeTextInput($request->address) : $member->address,
                idNumber: $member->id_number, // Cannot be changed
                birthDate: $member->birth_date, // Cannot be changed
                gender: $member->gender, // Cannot be changed
                occupation: $request->occupation ? $this->sanitizeTextInput($request->occupation) : $member->occupation,
                membershipType: $request->membership_type ?? $member->membership_type,
                status: $request->status ?? $member->status
            );

            // Update member
            $updatedMember = $this->memberService->updateMember($member->id, $memberDTO);

            Log::info('API member updated', [
                'member_id' => $member->id,
                'updated_by' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'changes' => $this->sanitizeLogData($request->only(['name', 'email', 'phone', 'status', 'membership_type'])),
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Member updated successfully',
                'data' => new MemberResource($updatedMember),
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API member update error', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while updating member', 500);
        }
    }

    /**
     * Remove the specified member
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid member ID', 400);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Find member with tenant isolation
            $member = Member::where('cooperative_id', $cooperativeId)->find($id);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            // Authorization check
            if (!Gate::allows('delete', $member)) {
                return $this->errorResponse('Insufficient permissions to delete this member', 403);
            }

            // Check if member can be deleted
            $canDelete = $this->memberService->canDeleteMember($member->id);

            if (!$canDelete['can_delete']) {
                return response()->json([
                    'success' => false,
                    'message' => $canDelete['reason'],
                    'details' => $canDelete['details'],
                ], 409);
            }

            // Store member info for logging before deletion
            $memberInfo = [
                'id' => $member->id,
                'member_number' => $member->member_number,
                'name' => $member->name,
                'cooperative_id' => $member->cooperative_id,
            ];

            // Delete member
            $this->memberService->deleteMember($member->id);

            Log::info('API member deleted', [
                'member_info' => $memberInfo,
                'deleted_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Member deleted successfully',
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Exception $e) {
            Log::error('API member deletion error', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while deleting member', 500);
        }
    }

    /**
     * Get member financial summary
     */
    public function financialSummary(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid member ID', 400);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Find member with tenant isolation
            $member = Member::where('cooperative_id', $cooperativeId)->find($id);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            // Authorization check
            if (!Gate::allows('view', $member)) {
                return $this->errorResponse('Insufficient permissions to view member financial summary', 403);
            }

            // Get financial summary with caching
            $cacheKey = "member_financial_summary:{$member->id}:" . now()->format('Y-m-d-H');

            $summary = Cache::remember($cacheKey, 1800, function () use ($member) { // 30 minutes cache
                return $this->memberService->getMemberFinancialSummary($member->id);
            });

            Log::info('API member financial summary retrieved', [
                'member_id' => $member->id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'cache_key' => $cacheKey,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => $summary,
            ]);

            // Add cache headers
            $response->headers->set('Cache-Control', 'public, max-age=1800');
            $response->headers->set('X-Cache-Key', $cacheKey);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Exception $e) {
            Log::error('API member financial summary error', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while retrieving financial summary', 500);
        }
    }

    /**
     * Update member status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            // ✅ SECURITY FIX: Validate ID parameter
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid member ID', 400);
            }

            // ✅ SECURITY FIX: Validate cooperative context
            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Find member with tenant isolation
            $member = Member::where('cooperative_id', $cooperativeId)->find($id);

            if (!$member) {
                return $this->errorResponse('Member not found', 404);
            }

            // Authorization check
            if (!Gate::allows('update', $member)) {
                return $this->errorResponse('Insufficient permissions to update member status', 403);
            }

            // ✅ SECURITY FIX: Enhanced validation
            $validated = $request->validate([
                'status' => 'required|string|in:active,inactive,suspended',
                'reason' => 'nullable|string|max:500|regex:/^[a-zA-Z0-9\s\-\_\.\,\!\?\(\)]+$/',
            ], [
                'reason.regex' => 'Reason contains invalid characters.',
            ]);

            // Update status with sanitized reason
            $updatedMember = $this->memberService->updateMemberStatus(
                memberId: $member->id,
                status: $validated['status'],
                reason: $validated['reason'] ? $this->sanitizeTextInput($validated['reason']) : null,
                updatedBy: $request->user()->id
            );

            Log::info('API member status updated', [
                'member_id' => $member->id,
                'old_status' => $member->status,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'],
                'updated_by' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Member status updated successfully',
                'data' => new MemberResource($updatedMember),
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API member status update error', [
                'error' => $e->getMessage(),
                'member_id' => $id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $request->user()->cooperative_id,
                'ip_address' => $request->ip(),
            ]);

            return $this->errorResponse('An error occurred while updating member status', 500);
        }
    }

    // ✅ SECURITY FIX: Input sanitization methods (same as CooperativeController)

    /**
     * Sanitize search input to prevent SQL injection
     */
    private function sanitizeSearchInput(?string $input): ?string
    {
        if (empty($input)) {
            return null;
        }

        $input = strip_tags($input);
        $input = trim($input);
        $input = preg_replace('/\s+/', ' ', $input);

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
        $limits = [
            'read' => ['limit' => 60, 'window' => 60],
            'write' => ['limit' => 10, 'window' => 60],
        ];

        $limit = $limits[$operation] ?? $limits['read'];

        $response->headers->set('X-RateLimit-Limit', $limit['limit']);
        $response->headers->set('X-RateLimit-Window', $limit['window']);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('API-Version', 'v1');

        return $response;
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
