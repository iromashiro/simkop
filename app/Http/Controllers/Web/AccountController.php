<?php
// app/Http/Controllers/Web/AccountController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Services\AccountService;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\DTOs\CreateAccountDTO;
use App\Domain\Accounting\DTOs\UpdateAccountDTO;
use App\Http\Requests\Web\Account\CreateAccountRequest;
use App\Http\Requests\Web\Account\UpdateAccountRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountService $accountService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_accounts');
    }

    /**
     * Display chart of accounts
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = Account::where('cooperative_id', $user->cooperative_id)
            ->with(['parent', 'children'])
            ->orderBy('code');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('code', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Type filter
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // Category filter
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        $accounts = $query->paginate(50)->withQueryString();

        // Get account statistics
        $statistics = $this->accountService->getAccountStatistics($user->cooperative_id);

        // Get account types and categories for filters
        $accountTypes = Account::ACCOUNT_TYPES;
        $accountCategories = Account::ACCOUNT_CATEGORIES;

        return view('accounts.index', compact(
            'accounts',
            'statistics',
            'accountTypes',
            'accountCategories'
        ));
    }

    /**
     * Show create account form
     */
    public function create(): View
    {
        $user = Auth::user();

        // Get parent accounts (only header accounts can have children)
        $parentAccounts = Account::where('cooperative_id', $user->cooperative_id)
            ->where('is_header', true)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $accountTypes = Account::ACCOUNT_TYPES;
        $accountCategories = Account::ACCOUNT_CATEGORIES;

        return view('accounts.create', compact(
            'parentAccounts',
            'accountTypes',
            'accountCategories'
        ));
    }

    /**
     * Store new account
     */
    public function store(CreateAccountRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateAccountDTO(
                cooperativeId: $user->cooperative_id,
                code: $request->code,
                name: $request->name,
                type: $request->type,
                category: $request->category,
                description: $request->description,
                parentId: $request->parent_id,
                isHeader: $request->boolean('is_header'),
                isActive: $request->boolean('is_active', true),
                normalBalance: $request->normal_balance,
                createdBy: $user->id
            );

            $account = $this->accountService->createAccount($dto);

            return redirect()
                ->route('accounts.show', $account)
                ->with('success', 'Account created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create account: ' . $e->getMessage()]);
        }
    }

    /**
     * Display account details
     */
    public function show(Account $account): View
    {
        $user = Auth::user();

        // Ensure account belongs to same cooperative
        if ($account->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $account->load(['parent', 'children', 'journalEntries.fiscalPeriod']);

        // Get account balance and transactions
        $balance = $this->accountService->getAccountBalance($account->id);
        $transactions = $this->accountService->getAccountTransactions($account->id, 20);

        // Get account statistics
        $statistics = $this->accountService->getAccountStatistics($user->cooperative_id, $account->id);

        return view('accounts.show', compact(
            'account',
            'balance',
            'transactions',
            'statistics'
        ));
    }

    /**
     * Show edit account form
     */
    public function edit(Account $account): View
    {
        $user = Auth::user();

        // Ensure account belongs to same cooperative
        if ($account->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        // Get parent accounts (exclude self and descendants)
        $parentAccounts = Account::where('cooperative_id', $user->cooperative_id)
            ->where('is_header', true)
            ->where('is_active', true)
            ->where('id', '!=', $account->id)
            ->orderBy('code')
            ->get();

        $accountTypes = Account::ACCOUNT_TYPES;
        $accountCategories = Account::ACCOUNT_CATEGORIES;

        return view('accounts.edit', compact(
            'account',
            'parentAccounts',
            'accountTypes',
            'accountCategories'
        ));
    }

    /**
     * Update account
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure account belongs to same cooperative
            if ($account->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $dto = new UpdateAccountDTO(
                name: $request->name,
                description: $request->description,
                parentId: $request->parent_id,
                isHeader: $request->boolean('is_header'),
                isActive: $request->boolean('is_active'),
                normalBalance: $request->normal_balance
            );

            $this->accountService->updateAccount($account->id, $dto);

            return redirect()
                ->route('accounts.show', $account)
                ->with('success', 'Account updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update account: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete account
     */
    public function destroy(Account $account): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure account belongs to same cooperative
            if ($account->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->accountService->deleteAccount($account->id);

            return redirect()
                ->route('accounts.index')
                ->with('success', 'Account deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete account: ' . $e->getMessage()]);
        }
    }

    /**
     * Show account hierarchy
     */
    public function hierarchy(): View
    {
        $user = Auth::user();

        $accountTree = $this->accountService->getAccountHierarchy($user->cooperative_id);

        return view('accounts.hierarchy', compact('accountTree'));
    }

    /**
     * Show trial balance
     */
    public function trialBalance(Request $request): View
    {
        $request->validate([
            'as_of_date' => 'date',
            'fiscal_period_id' => 'integer|exists:fiscal_periods,id',
        ]);

        $user = Auth::user();
        $asOfDate = $request->as_of_date ? \Carbon\Carbon::parse($request->as_of_date) : now();
        $fiscalPeriodId = $request->fiscal_period_id;

        $trialBalance = $this->accountService->getTrialBalance(
            $user->cooperative_id,
            $asOfDate,
            $fiscalPeriodId
        );

        // Get fiscal periods for filter
        $fiscalPeriods = \App\Domain\Accounting\Models\FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('accounts.trial-balance', compact(
            'trialBalance',
            'asOfDate',
            'fiscalPeriods',
            'fiscalPeriodId'
        ));
    }

    /**
     * Import chart of accounts
     */
    public function import(): View
    {
        return view('accounts.import');
    }

    /**
     * Process chart of accounts import
     */
    public function processImport(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,xlsx|max:2048',
            'has_header' => 'boolean',
        ]);

        try {
            $user = Auth::user();

            $result = $this->accountService->importAccounts(
                $user->cooperative_id,
                $request->file('file'),
                $request->boolean('has_header', true),
                $user->id
            );

            return redirect()
                ->route('accounts.index')
                ->with('success', "Imported {$result['imported']} accounts successfully. {$result['skipped']} skipped.");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to import accounts: ' . $e->getMessage()]);
        }
    }

    /**
     * Export chart of accounts
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();

        return $this->accountService->exportAccounts($user->cooperative_id, $request->all());
    }

    /**
     * Toggle account status
     */
    public function toggleStatus(Account $account): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure account belongs to same cooperative
            if ($account->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $account->update(['is_active' => !$account->is_active]);

            $status = $account->is_active ? 'activated' : 'deactivated';

            return back()->with('success', "Account {$status} successfully");
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to toggle account status: ' . $e->getMessage()]);
        }
    }
}
