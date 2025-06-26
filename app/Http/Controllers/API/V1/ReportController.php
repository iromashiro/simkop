<?php
// app/Http/Controllers/API/V1/ReportController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Reporting\Services\ReportGenerationService;
use App\Domain\Reporting\DTOs\ReportParametersDTO;
use App\Domain\Reporting\Models\GeneratedReport;
use App\Http\Requests\API\Report\GenerateReportRequest;
use App\Http\Resources\Report\ReportResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * PRODUCTION READY: Report API Controller with ENHANCED SECURITY
 * SRS Reference: Section 2.5 - Reporting Requirements
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportGenerationService $reportService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.aware');
        $this->middleware('throttle:20,1')->only(['index', 'show', 'download']);
        $this->middleware('throttle:5,1')->only(['generate', 'regenerate']);
    }

    /**
     * Get available reports
     *
     * @OA\Get(
     *     path="/api/v1/reports",
     *     summary="Get available reports",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", enum={"financial","member","operational"}),
     *     @OA\Parameter(name="status", in="query", enum={"pending","completed","failed"}),
     *     @OA\Response(response=200, description="Reports retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', GeneratedReport::class)) {
                return $this->errorResponse('Insufficient permissions to view reports', 403);
            }

            $validated = $request->validate([
                'page' => 'integer|min:1|max:1000',
                'per_page' => 'integer|min:1|max:50',
                'type' => 'nullable|string|in:financial,member,operational,shu,budget',
                'status' => 'nullable|string|in:pending,completed,failed',
                'date_from' => 'nullable|date|before_or_equal:today',
                'date_to' => 'nullable|date|after_or_equal:date_from|before_or_equal:today',
                'search' => [
                    'nullable',
                    'string',
                    'max:255',
                    'regex:/^[a-zA-Z0-9\s\-\_\.]+$/',
                ],
            ]);

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            $query = GeneratedReport::where('cooperative_id', $cooperativeId);

            if (!empty($validated['type'])) {
                $query->where('report_type', $validated['type']);
            }

            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            if (!empty($validated['date_from'])) {
                $query->where('created_at', '>=', $validated['date_from']);
            }

            if (!empty($validated['date_to'])) {
                $query->where('created_at', '<=', $validated['date_to'] . ' 23:59:59');
            }

            if (!empty($validated['search'])) {
                $search = $this->sanitizeSearchInput($validated['search']);
                $query->where(function ($q) use ($search) {
                    $q->where('report_name', 'LIKE', "%{$search}%")
                        ->orWhere('description', 'LIKE', "%{$search}%");
                });
            }

            $reports = $query->with(['generatedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate($validated['per_page'] ?? 15);

            Log::info('API reports list retrieved', [
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'filters' => $this->sanitizeLogData($validated),
                'total_results' => $reports->total(),
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => [
                    'reports' => ReportResource::collection($reports),
                    'pagination' => [
                        'current_page' => $reports->currentPage(),
                        'last_page' => $reports->lastPage(),
                        'per_page' => $reports->perPage(),
                        'total' => $reports->total(),
                    ],
                    'summary' => [
                        'total_reports' => $reports->total(),
                        'completed_reports' => $reports->where('status', 'completed')->count(),
                        'pending_reports' => $reports->where('status', 'pending')->count(),
                        'failed_reports' => $reports->where('status', 'failed')->count(),
                    ],
                ],
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API reports index error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving reports', 500);
        }
    }

    /**
     * Generate new report
     *
     * @OA\Post(
     *     path="/api/v1/reports/generate",
     *     summary="Generate new report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"report_type","start_date","end_date"},
     *             @OA\Property(property="report_type", type="string", enum={"balance_sheet","income_statement","cash_flow","member_savings","loan_receivables"}),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="format", type="string", enum={"pdf","excel","csv"}, default="pdf"),
     *             @OA\Property(property="parameters", type="object")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Report generation started")
     * )
     */
    public function generate(GenerateReportRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('create', GeneratedReport::class)) {
                return $this->errorResponse('Insufficient permissions to generate reports', 403);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Validate date range
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);

            if ($startDate->gt($endDate)) {
                return $this->errorResponse('Start date must be before or equal to end date', 400);
            }

            if ($startDate->diffInDays($endDate) > 365) {
                return $this->errorResponse('Date range cannot exceed 365 days', 400);
            }

            // Validate report type
            $allowedReports = [
                'balance_sheet',
                'income_statement',
                'cash_flow',
                'equity_changes',
                'financial_notes',
                'member_savings',
                'loan_receivables',
                'non_performing_loans',
                'shu_distribution',
                'budget_variance'
            ];

            if (!in_array($request->report_type, $allowedReports)) {
                return $this->errorResponse('Invalid report type', 400);
            }

            // Check for duplicate recent reports
            $recentReport = GeneratedReport::where('cooperative_id', $cooperativeId)
                ->where('report_type', $request->report_type)
                ->where('start_date', $startDate)
                ->where('end_date', $endDate)
                ->where('created_at', '>', now()->subHours(1))
                ->where('status', '!=', 'failed')
                ->first();

            if ($recentReport) {
                return response()->json([
                    'success' => false,
                    'message' => 'A similar report was generated recently',
                    'data' => [
                        'existing_report_id' => $recentReport->id,
                        'status' => $recentReport->status,
                    ],
                ], 409);
            }

            $reportParametersDTO = new ReportParametersDTO(
                reportType: $request->report_type,
                cooperativeId: $cooperativeId,
                startDate: $startDate,
                endDate: $endDate,
                format: $request->get('format', 'pdf'),
                parameters: $request->get('parameters', [])
            );

            $report = $this->reportService->generateReport($reportParametersDTO);

            Log::info('API report generation started', [
                'report_id' => $report->id,
                'report_type' => $request->report_type,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'format' => $request->get('format', 'pdf'),
                'cooperative_id' => $cooperativeId,
                'generated_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Report generation started successfully',
                'data' => new ReportResource($report),
            ], 201);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API report generation error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'request_data' => $this->sanitizeLogData($request->all()),
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while generating report', 500);
        }
    }

    /**
     * Get report details
     *
     * @OA\Get(
     *     path="/api/v1/reports/{id}",
     *     summary="Get report details",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, type="integer"),
     *     @OA\Response(response=200, description="Report details retrieved successfully")
     * )
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid report ID', 400);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            $report = GeneratedReport::where('cooperative_id', $cooperativeId)
                ->with(['generatedBy'])
                ->find($id);

            if (!$report) {
                return $this->errorResponse('Report not found', 404);
            }

            if (!Gate::allows('view', $report)) {
                return $this->errorResponse('Insufficient permissions to view this report', 403);
            }

            Log::info('API report details retrieved', [
                'report_id' => $report->id,
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => new ReportResource($report),
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Exception $e) {
            Log::error('API report show error', [
                'error' => $e->getMessage(),
                'report_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving report details', 500);
        }
    }

    /**
     * Download report file
     *
     * @OA\Get(
     *     path="/api/v1/reports/{id}/download",
     *     summary="Download report file",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, type="integer"),
     *     @OA\Response(response=200, description="Report file downloaded successfully")
     * )
     */
    public function download(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid report ID', 400);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            $report = GeneratedReport::where('cooperative_id', $cooperativeId)->find($id);

            if (!$report) {
                return $this->errorResponse('Report not found', 404);
            }

            if (!Gate::allows('view', $report)) {
                return $this->errorResponse('Insufficient permissions to download this report', 403);
            }

            if ($report->status !== 'completed') {
                return $this->errorResponse('Report is not ready for download', 400);
            }

            if (!$report->file_path || !Storage::exists($report->file_path)) {
                return $this->errorResponse('Report file not found', 404);
            }

            // Update download count
            $report->increment('download_count');
            $report->update(['last_downloaded_at' => now()]);

            Log::info('API report downloaded', [
                'report_id' => $report->id,
                'report_type' => $report->report_type,
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'download_count' => $report->download_count,
                'ip_address' => $request->ip(),
            ]);

            // Return download URL or file content based on storage configuration
            $downloadUrl = Storage::temporaryUrl($report->file_path, now()->addMinutes(30));

            $response = response()->json([
                'success' => true,
                'message' => 'Report ready for download',
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => basename($report->file_path),
                    'file_size' => Storage::size($report->file_path),
                    'expires_at' => now()->addMinutes(30)->toISOString(),
                ],
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Exception $e) {
            Log::error('API report download error', [
                'error' => $e->getMessage(),
                'report_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while preparing report download', 500);
        }
    }

    /**
     * Get available report types
     *
     * @OA\Get(
     *     path="/api/v1/reports/types",
     *     summary="Get available report types",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, description="Report types retrieved successfully")
     * )
     */
    public function types(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', GeneratedReport::class)) {
                return $this->errorResponse('Insufficient permissions to view report types', 403);
            }

            $cacheKey = "report_types:v1";

            $reportTypes = Cache::remember($cacheKey, 3600, function () {
                return [
                    'financial' => [
                        [
                            'type' => 'balance_sheet',
                            'name' => 'Balance Sheet',
                            'description' => 'Statement of financial position',
                            'category' => 'financial',
                            'formats' => ['pdf', 'excel', 'csv'],
                        ],
                        [
                            'type' => 'income_statement',
                            'name' => 'Income Statement',
                            'description' => 'Statement of comprehensive income',
                            'category' => 'financial',
                            'formats' => ['pdf', 'excel', 'csv'],
                        ],
                        [
                            'type' => 'cash_flow',
                            'name' => 'Cash Flow Statement',
                            'description' => 'Statement of cash flows',
                            'category' => 'financial',
                            'formats' => ['pdf', 'excel', 'csv'],
                        ],
                        [
                            'type' => 'equity_changes',
                            'name' => 'Statement of Changes in Equity',
                            'description' => 'Changes in cooperative equity',
                            'category' => 'financial',
                            'formats' => ['pdf', 'excel'],
                        ],
                    ],
                    'member' => [
                        [
                            'type' => 'member_savings',
                            'name' => 'Member Savings Report',
                            'description' => 'Detailed member savings information',
                            'category' => 'member',
                            'formats' => ['pdf', 'excel', 'csv'],
                        ],
                        [
                            'type' => 'loan_receivables',
                            'name' => 'Loan Receivables Report',
                            'description' => 'Outstanding loans and receivables',
                            'category' => 'member',
                            'formats' => ['pdf', 'excel', 'csv'],
                        ],
                    ],
                    'operational' => [
                        [
                            'type' => 'shu_distribution',
                            'name' => 'SHU Distribution Report',
                            'description' => 'Remaining business results distribution',
                            'category' => 'operational',
                            'formats' => ['pdf', 'excel'],
                        ],
                        [
                            'type' => 'budget_variance',
                            'name' => 'Budget Variance Report',
                            'description' => 'Budget vs actual analysis',
                            'category' => 'operational',
                            'formats' => ['pdf', 'excel'],
                        ],
                    ],
                ];
            });

            $response = response()->json([
                'success' => true,
                'data' => [
                    'report_types' => $reportTypes,
                    'total_types' => collect($reportTypes)->flatten(1)->count(),
                    'categories' => array_keys($reportTypes),
                ],
            ]);

            $response->headers->set('Cache-Control', 'public, max-age=3600');
            return $this->addSecurityHeaders($response, 'read');
        } catch (\Exception $e) {
            Log::error('API report types error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving report types', 500);
        }
    }

    /**
     * Delete report
     *
     * @OA\Delete(
     *     path="/api/v1/reports/{id}",
     *     summary="Delete report",
     *     tags={"Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, type="integer"),
     *     @OA\Response(response=200, description="Report deleted successfully")
     * )
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            if ($id <= 0 || $id > 2147483647) {
                return $this->errorResponse('Invalid report ID', 400);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            $report = GeneratedReport::where('cooperative_id', $cooperativeId)->find($id);

            if (!$report) {
                return $this->errorResponse('Report not found', 404);
            }

            if (!Gate::allows('delete', $report)) {
                return $this->errorResponse('Insufficient permissions to delete this report', 403);
            }

            // Store report info for logging
            $reportInfo = [
                'id' => $report->id,
                'type' => $report->report_type,
                'name' => $report->report_name,
                'file_path' => $report->file_path,
            ];

            // Delete file if exists
            if ($report->file_path && Storage::exists($report->file_path)) {
                Storage::delete($report->file_path);
            }

            // Delete report record
            $report->delete();

            Log::info('API report deleted', [
                'report_info' => $reportInfo,
                'deleted_by' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Report deleted successfully',
            ]);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Exception $e) {
            Log::error('API report deletion error', [
                'error' => $e->getMessage(),
                'report_id' => $id,
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while deleting report', 500);
        }
    }

    // Security helper methods
    private function sanitizeSearchInput(?string $input): ?string
    {
        if (empty($input)) return null;
        return substr(strip_tags(trim($input)), 0, 255);
    }

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

    private function addSecurityHeaders(JsonResponse $response, string $operation): JsonResponse
    {
        $limits = ['read' => 20, 'write' => 5];
        $response->headers->set('X-RateLimit-Limit', $limits[$operation] ?? 20);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('API-Version', 'v1');
        return $response;
    }

    private function errorResponse(string $message, int $code): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $code);
    }

    private function validationErrorResponse(\Illuminate\Validation\ValidationException $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors(),
        ], 422);
    }
}
