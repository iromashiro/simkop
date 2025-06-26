<?php
// app/Http/Controllers/API/V1/FinancialController.php
namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Domain\Financial\Services\FinancialTransactionService;
use App\Domain\Financial\Services\JournalEntryService;
use App\Domain\Financial\DTOs\TransactionDTO;
use App\Domain\Financial\DTOs\JournalEntryDTO;
use App\Domain\Financial\Models\JournalEntry;
use App\Domain\Financial\Models\Account;
use App\Http\Requests\API\Financial\CreateTransactionRequest;
use App\Http\Requests\API\Financial\CreateJournalEntryRequest;
use App\Http\Resources\Financial\TransactionResource;
use App\Http\Resources\Financial\JournalEntryResource;
use App\Http\Resources\Financial\AccountResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Cache;

/**
 * PRODUCTION READY: Financial API Controller with ENHANCED SECURITY
 * SRS Reference: Section 2.4 - Financial Management Requirements
 */
class FinancialController extends Controller
{
    public function __construct(
        private readonly FinancialTransactionService $transactionService,
        private readonly JournalEntryService $journalService
    ) {
        $this->middleware('auth:sanctum');
        $this->middleware('tenant.aware');
        $this->middleware('throttle:30,1')->only(['index', 'show', 'accounts', 'balances']);
        $this->middleware('throttle:10,1')->only(['store', 'update', 'destroy']);
    }

    /**
     * Get financial transactions
     *
     * @OA\Get(
     *     path="/api/v1/financial/transactions",
     *     summary="Get financial transactions",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", enum={"savings","loan","shu","fee"}),
     *     @OA\Parameter(name="status", in="query", enum={"pending","approved","rejected"}),
     *     @OA\Parameter(name="date_from", in="query", type="string", format="date"),
     *     @OA\Parameter(name="date_to", in="query", type="string", format="date"),
     *     @OA\Response(response=200, description="Transactions retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', JournalEntry::class)) {
                return $this->errorResponse('Insufficient permissions to view transactions', 403);
            }

            $validated = $request->validate([
                'page' => 'integer|min:1|max:10000',
                'per_page' => 'integer|min:1|max:100',
                'type' => 'nullable|string|in:savings,loan,shu,fee,deposit,withdrawal',
                'status' => 'nullable|string|in:pending,approved,rejected',
                'date_from' => 'nullable|date|before_or_equal:today',
                'date_to' => 'nullable|date|after_or_equal:date_from|before_or_equal:today',
                'member_id' => 'nullable|integer|exists:members,id',
                'amount_min' => 'nullable|numeric|min:0',
                'amount_max' => 'nullable|numeric|min:amount_min',
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

            $filters = [
                'type' => $validated['type'] ?? null,
                'status' => $validated['status'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'member_id' => $validated['member_id'] ?? null,
                'amount_min' => $validated['amount_min'] ?? null,
                'amount_max' => $validated['amount_max'] ?? null,
                'search' => $this->sanitizeSearchInput($validated['search'] ?? null),
            ];

            $transactions = $this->transactionService->getTransactionsList(
                cooperativeId: $cooperativeId,
                filters: $filters,
                perPage: $validated['per_page'] ?? 15
            );

            Log::info('API financial transactions retrieved', [
                'user_id' => $request->user()->id,
                'cooperative_id' => $cooperativeId,
                'filters' => $this->sanitizeLogData($filters),
                'total_results' => $transactions->total(),
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'data' => [
                    'transactions' => TransactionResource::collection($transactions),
                    'pagination' => [
                        'current_page' => $transactions->currentPage(),
                        'last_page' => $transactions->lastPage(),
                        'per_page' => $transactions->perPage(),
                        'total' => $transactions->total(),
                        'from' => $transactions->firstItem(),
                        'to' => $transactions->lastItem(),
                    ],
                    'summary' => [
                        'total_amount' => $transactions->sum('amount'),
                        'pending_count' => $transactions->where('status', 'pending')->count(),
                        'approved_count' => $transactions->where('status', 'approved')->count(),
                    ],
                ],
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API financial transactions error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving transactions', 500);
        }
    }

    /**
     * Create new financial transaction
     *
     * @OA\Post(
     *     path="/api/v1/financial/transactions",
     *     summary="Create financial transaction",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"type","amount","member_id","description"},
     *             @OA\Property(property="type", type="string", enum={"savings","loan","deposit","withdrawal"}),
     *             @OA\Property(property="amount", type="number", minimum=0.01),
     *             @OA\Property(property="member_id", type="integer"),
     *             @OA\Property(property="description", type="string", maxLength=500),
     *             @OA\Property(property="reference_number", type="string", maxLength=50)
     *         )
     *     ),
     *     @OA\Response(response=201, description="Transaction created successfully")
     * )
     */
    public function store(CreateTransactionRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('create', JournalEntry::class)) {
                return $this->errorResponse('Insufficient permissions to create transaction', 403);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Validate amount limits
            $amount = $request->amount;
            if ($amount <= 0 || $amount > 1000000000) {
                return $this->errorResponse('Invalid transaction amount', 400);
            }

            // Validate member belongs to cooperative
            $member = \App\Domain\Member\Models\Member::where('cooperative_id', $cooperativeId)
                ->where('id', $request->member_id)
                ->first();

            if (!$member) {
                return $this->errorResponse('Member not found in your cooperative', 404);
            }

            $transactionDTO = new TransactionDTO(
                cooperativeId: $cooperativeId,
                memberId: $request->member_id,
                type: $request->type,
                amount: $amount,
                description: $this->sanitizeTextInput($request->description),
                referenceNumber: $this->sanitizeAlphanumeric($request->reference_number),
                createdBy: $request->user()->id
            );

            $transaction = $this->transactionService->createTransaction($transactionDTO);

            Log::info('API financial transaction created', [
                'transaction_id' => $transaction->id,
                'type' => $transaction->type,
                'amount' => $transaction->amount,
                'member_id' => $transaction->member_id,
                'cooperative_id' => $cooperativeId,
                'created_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => new TransactionResource($transaction),
            ], 201);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API financial transaction creation error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'request_data' => $this->sanitizeLogData($request->except(['amount'])),
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while creating transaction', 500);
        }
    }

    /**
     * Get journal entries
     *
     * @OA\Get(
     *     path="/api/v1/financial/journal-entries",
     *     summary="Get journal entries",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="account_id", in="query", type="integer"),
     *     @OA\Parameter(name="is_approved", in="query", type="boolean"),
     *     @OA\Response(response=200, description="Journal entries retrieved successfully")
     * )
     */
    public function journalEntries(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', JournalEntry::class)) {
                return $this->errorResponse('Insufficient permissions to view journal entries', 403);
            }

            $validated = $request->validate([
                'page' => 'integer|min:1|max:10000',
                'per_page' => 'integer|min:1|max:100',
                'account_id' => 'nullable|integer|exists:accounts,id',
                'is_approved' => 'nullable|boolean',
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

            $filters = [
                'account_id' => $validated['account_id'] ?? null,
                'is_approved' => $validated['is_approved'] ?? null,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'search' => $this->sanitizeSearchInput($validated['search'] ?? null),
            ];

            $journalEntries = $this->journalService->getJournalEntriesList(
                cooperativeId: $cooperativeId,
                filters: $filters,
                perPage: $validated['per_page'] ?? 15
            );

            $response = response()->json([
                'success' => true,
                'data' => [
                    'journal_entries' => JournalEntryResource::collection($journalEntries),
                    'pagination' => [
                        'current_page' => $journalEntries->currentPage(),
                        'last_page' => $journalEntries->lastPage(),
                        'per_page' => $journalEntries->perPage(),
                        'total' => $journalEntries->total(),
                    ],
                ],
            ]);

            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API journal entries error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving journal entries', 500);
        }
    }

    /**
     * Create journal entry
     *
     * @OA\Post(
     *     path="/api/v1/financial/journal-entries",
     *     summary="Create journal entry",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"description","lines"},
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="reference_number", type="string"),
     *             @OA\Property(property="transaction_date", type="string", format="date"),
     *             @OA\Property(property="lines", type="array", @OA\Items(
     *                 @OA\Property(property="account_id", type="integer"),
     *                 @OA\Property(property="debit_amount", type="number"),
     *                 @OA\Property(property="credit_amount", type="number"),
     *                 @OA\Property(property="description", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Journal entry created successfully")
     * )
     */
    public function createJournalEntry(CreateJournalEntryRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('create', JournalEntry::class)) {
                return $this->errorResponse('Insufficient permissions to create journal entry', 403);
            }

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            // Validate journal lines balance
            $totalDebits = collect($request->lines)->sum('debit_amount');
            $totalCredits = collect($request->lines)->sum('credit_amount');

            if (abs($totalDebits - $totalCredits) > 0.01) {
                return $this->errorResponse('Journal entry is not balanced. Debits must equal credits.', 400);
            }

            // Validate all accounts belong to cooperative
            $accountIds = collect($request->lines)->pluck('account_id')->unique();
            $validAccounts = Account::where('cooperative_id', $cooperativeId)
                ->whereIn('id', $accountIds)
                ->count();

            if ($validAccounts !== $accountIds->count()) {
                return $this->errorResponse('One or more accounts do not belong to your cooperative', 400);
            }

            $journalEntryDTO = new JournalEntryDTO(
                cooperativeId: $cooperativeId,
                description: $this->sanitizeTextInput($request->description),
                referenceNumber: $this->sanitizeAlphanumeric($request->reference_number),
                transactionDate: $request->transaction_date ?
                    \Carbon\Carbon::parse($request->transaction_date) : now(),
                lines: collect($request->lines)->map(function ($line) {
                    return [
                        'account_id' => $line['account_id'],
                        'debit_amount' => $line['debit_amount'] ?? 0,
                        'credit_amount' => $line['credit_amount'] ?? 0,
                        'description' => $this->sanitizeTextInput($line['description'] ?? ''),
                    ];
                })->toArray(),
                createdBy: $request->user()->id
            );

            $journalEntry = $this->journalService->createJournalEntry($journalEntryDTO);

            Log::info('API journal entry created', [
                'journal_entry_id' => $journalEntry->id,
                'reference_number' => $journalEntry->reference_number,
                'total_amount' => $totalDebits,
                'lines_count' => count($request->lines),
                'cooperative_id' => $cooperativeId,
                'created_by' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);

            $response = response()->json([
                'success' => true,
                'message' => 'Journal entry created successfully',
                'data' => new JournalEntryResource($journalEntry),
            ], 201);

            return $this->addSecurityHeaders($response, 'write');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API journal entry creation error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while creating journal entry', 500);
        }
    }

    /**
     * Get chart of accounts
     *
     * @OA\Get(
     *     path="/api/v1/financial/accounts",
     *     summary="Get chart of accounts",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="type", in="query", enum={"ASSET","LIABILITY","EQUITY","REVENUE","EXPENSE"}),
     *     @OA\Parameter(name="is_active", in="query", type="boolean"),
     *     @OA\Response(response=200, description="Accounts retrieved successfully")
     * )
     */
    public function accounts(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', Account::class)) {
                return $this->errorResponse('Insufficient permissions to view accounts', 403);
            }

            $validated = $request->validate([
                'type' => 'nullable|string|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
                'is_active' => 'nullable|boolean',
                'parent_id' => 'nullable|integer|exists:accounts,id',
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

            $cacheKey = "accounts:{$cooperativeId}:" . md5(serialize($validated));

            $accounts = Cache::remember($cacheKey, 1800, function () use ($cooperativeId, $validated) {
                $query = Account::where('cooperative_id', $cooperativeId);

                if (!empty($validated['type'])) {
                    $query->where('type', $validated['type']);
                }

                if (isset($validated['is_active'])) {
                    $query->where('is_active', $validated['is_active']);
                }

                if (!empty($validated['parent_id'])) {
                    $query->where('parent_id', $validated['parent_id']);
                }

                if (!empty($validated['search'])) {
                    $search = $this->sanitizeSearchInput($validated['search']);
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%")
                            ->orWhere('code', 'LIKE', "%{$search}%");
                    });
                }

                return $query->with(['parent', 'children'])
                    ->orderBy('code')
                    ->get();
            });

            $response = response()->json([
                'success' => true,
                'data' => [
                    'accounts' => AccountResource::collection($accounts),
                    'summary' => [
                        'total_accounts' => $accounts->count(),
                        'by_type' => $accounts->groupBy('type')->map->count(),
                        'active_accounts' => $accounts->where('is_active', true)->count(),
                    ],
                ],
            ]);

            $response->headers->set('Cache-Control', 'public, max-age=1800');
            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API accounts error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving accounts', 500);
        }
    }

    /**
     * Get account balances
     *
     * @OA\Get(
     *     path="/api/v1/financial/balances",
     *     summary="Get account balances",
     *     tags={"Financial"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="as_of_date", in="query", type="string", format="date"),
     *     @OA\Parameter(name="account_type", in="query", enum={"ASSET","LIABILITY","EQUITY","REVENUE","EXPENSE"}),
     *     @OA\Response(response=200, description="Balances retrieved successfully")
     * )
     */
    public function balances(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('viewAny', Account::class)) {
                return $this->errorResponse('Insufficient permissions to view balances', 403);
            }

            $validated = $request->validate([
                'as_of_date' => 'nullable|date|before_or_equal:today',
                'account_type' => 'nullable|string|in:ASSET,LIABILITY,EQUITY,REVENUE,EXPENSE',
                'account_ids' => 'nullable|array',
                'account_ids.*' => 'integer|exists:accounts,id',
            ]);

            $cooperativeId = $request->user()->cooperative_id;
            if (!$cooperativeId) {
                return $this->errorResponse('Invalid cooperative context', 400);
            }

            $asOfDate = $validated['as_of_date'] ?? now()->toDateString();
            $accountType = $validated['account_type'] ?? null;
            $accountIds = $validated['account_ids'] ?? null;

            $cacheKey = "balances:{$cooperativeId}:{$asOfDate}:" . md5(serialize($validated));

            $balances = Cache::remember($cacheKey, 900, function () use ($cooperativeId, $asOfDate, $accountType, $accountIds) {
                return $this->journalService->getAccountBalances(
                    cooperativeId: $cooperativeId,
                    asOfDate: $asOfDate,
                    accountType: $accountType,
                    accountIds: $accountIds
                );
            });

            $response = response()->json([
                'success' => true,
                'data' => [
                    'balances' => $balances,
                    'as_of_date' => $asOfDate,
                    'summary' => [
                        'total_assets' => $balances->where('account_type', 'ASSET')->sum('balance'),
                        'total_liabilities' => $balances->where('account_type', 'LIABILITY')->sum('balance'),
                        'total_equity' => $balances->where('account_type', 'EQUITY')->sum('balance'),
                        'total_revenue' => $balances->where('account_type', 'REVENUE')->sum('balance'),
                        'total_expenses' => $balances->where('account_type', 'EXPENSE')->sum('balance'),
                    ],
                ],
            ]);

            $response->headers->set('Cache-Control', 'public, max-age=900');
            return $this->addSecurityHeaders($response, 'read');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Exception $e) {
            Log::error('API balances error', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()->id,
                'ip_address' => $request->ip(),
            ]);
            return $this->errorResponse('An error occurred while retrieving balances', 500);
        }
    }

    // Security helper methods (same as previous controllers)
    private function sanitizeSearchInput(?string $input): ?string
    {
        if (empty($input)) return null;
        return substr(strip_tags(trim($input)), 0, 255);
    }

    private function sanitizeTextInput(?string $input): ?string
    {
        if (empty($input)) return null;
        return strip_tags(trim($input));
    }

    private function sanitizeAlphanumeric(?string $input): ?string
    {
        if (empty($input)) return null;
        return preg_replace('/[^a-zA-Z0-9\-\_]/', '', $input);
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
        $limits = ['read' => 30, 'write' => 10];
        $response->headers->set('X-RateLimit-Limit', $limits[$operation] ?? 30);
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
