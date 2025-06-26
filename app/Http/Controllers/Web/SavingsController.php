<?php
// app/Http/Controllers/Web/SavingsController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Savings\Services\SavingsService;
use App\Domain\Savings\Models\SavingsAccount;
use App\Domain\Savings\Models\SavingsTransaction;
use App\Domain\Savings\DTOs\CreateSavingsAccountDTO;
use App\Domain\Savings\DTOs\SavingsTransactionDTO;
use App\Http\Requests\Web\Savings\CreateSavingsAccountRequest;
use App\Http\Requests\Web\Savings\SavingsTransactionRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class SavingsController extends Controller
{
    public function __construct(
        private readonly SavingsService $savingsService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_savings');
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = SavingsAccount::where('cooperative_id', $user->cooperative_id)
            ->with(['member', 'savingsProduct'])
            ->orderBy('account_number');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'ILIKE', "%{$search}%")
                    ->orWhereHas('member', function ($mq) use ($search) {
                        $mq->where('full_name', 'ILIKE', "%{$search}%");
                    });
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $savingsAccounts = $query->paginate(20)->withQueryString();
        $statistics = $this->savingsService->getSavingsStatistics($user->cooperative_id);

        return view('savings.index', compact('savingsAccounts', 'statistics'));
    }

    public function create(): View
    {
        $user = Auth::user();

        $members = \App\Domain\Member\Models\Member::where('cooperative_id', $user->cooperative_id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $savingsProducts = \App\Domain\Savings\Models\SavingsProduct::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('savings.create', compact('members', 'savingsProducts'));
    }

    public function store(CreateSavingsAccountRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateSavingsAccountDTO(
                cooperativeId: $user->cooperative_id,
                memberId: $request->member_id,
                savingsProductId: $request->savings_product_id,
                initialDeposit: $request->initial_deposit ?? 0,
                createdBy: $user->id
            );

            $savingsAccount = $this->savingsService->createSavingsAccount($dto);

            return redirect()
                ->route('savings.show', $savingsAccount)
                ->with('success', 'Savings account created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create savings account: ' . $e->getMessage()]);
        }
    }

    public function show(SavingsAccount $savingsAccount): View
    {
        $user = Auth::user();

        if ($savingsAccount->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $savingsAccount->load(['member', 'savingsProduct']);

        $transactions = SavingsTransaction::where('savings_account_id', $savingsAccount->id)
            ->with(['processedBy'])
            ->orderBy('transaction_date', 'desc')
            ->paginate(20);

        $statistics = $this->savingsService->getAccountStatistics($savingsAccount->id);

        return view('savings.show', compact('savingsAccount', 'transactions', 'statistics'));
    }

    public function deposit(SavingsAccount $savingsAccount): View
    {
        $user = Auth::user();

        if ($savingsAccount->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        return view('savings.deposit', compact('savingsAccount'));
    }

    public function processDeposit(SavingsTransactionRequest $request, SavingsAccount $savingsAccount): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($savingsAccount->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $dto = new SavingsTransactionDTO(
                savingsAccountId: $savingsAccount->id,
                type: 'deposit',
                amount: $request->amount,
                description: $request->description,
                transactionDate: \Carbon\Carbon::parse($request->transaction_date),
                processedBy: $user->id
            );

            $transaction = $this->savingsService->processTransaction($dto);

            return redirect()
                ->route('savings.show', $savingsAccount)
                ->with('success', 'Deposit processed successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process deposit: ' . $e->getMessage()]);
        }
    }

    public function withdrawal(SavingsAccount $savingsAccount): View
    {
        $user = Auth::user();

        if ($savingsAccount->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        return view('savings.withdrawal', compact('savingsAccount'));
    }

    public function processWithdrawal(SavingsTransactionRequest $request, SavingsAccount $savingsAccount): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($savingsAccount->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $dto = new SavingsTransactionDTO(
                savingsAccountId: $savingsAccount->id,
                type: 'withdrawal',
                amount: $request->amount,
                description: $request->description,
                transactionDate: \Carbon\Carbon::parse($request->transaction_date),
                processedBy: $user->id
            );

            $transaction = $this->savingsService->processTransaction($dto);

            return redirect()
                ->route('savings.show', $savingsAccount)
                ->with('success', 'Withdrawal processed successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process withdrawal: ' . $e->getMessage()]);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        return $this->savingsService->exportSavingsAccounts($user->cooperative_id, $request->all());
    }
}
