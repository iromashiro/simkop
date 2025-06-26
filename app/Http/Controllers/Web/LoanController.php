<?php
// app/Http/Controllers/Web/LoanController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Loan\Services\LoanService;
use App\Domain\Loan\Models\LoanAccount;
use App\Domain\Loan\Models\LoanPayment;
use App\Domain\Loan\DTOs\CreateLoanAccountDTO;
use App\Domain\Loan\DTOs\LoanPaymentDTO;
use App\Http\Requests\Web\Loan\CreateLoanAccountRequest;
use App\Http\Requests\Web\Loan\LoanPaymentRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loanService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_loans');
    }

    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = LoanAccount::where('cooperative_id', $user->cooperative_id)
            ->with(['member', 'loanProduct'])
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

        $loanAccounts = $query->paginate(20)->withQueryString();
        $statistics = $this->loanService->getLoanStatistics($user->cooperative_id);

        return view('loans.index', compact('loanAccounts', 'statistics'));
    }

    public function create(): View
    {
        $user = Auth::user();

        $members = \App\Domain\Member\Models\Member::where('cooperative_id', $user->cooperative_id)
            ->where('status', 'active')
            ->orderBy('full_name')
            ->get();

        $loanProducts = \App\Domain\Loan\Models\LoanProduct::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('loans.create', compact('members', 'loanProducts'));
    }

    public function store(CreateLoanAccountRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateLoanAccountDTO(
                cooperativeId: $user->cooperative_id,
                memberId: $request->member_id,
                loanProductId: $request->loan_product_id,
                principalAmount: $request->principal_amount,
                interestRate: $request->interest_rate,
                termMonths: $request->term_months,
                disbursementDate: \Carbon\Carbon::parse($request->disbursement_date),
                purpose: $request->purpose,
                createdBy: $user->id
            );

            $loanAccount = $this->loanService->createLoanAccount($dto);

            return redirect()
                ->route('loans.show', $loanAccount)
                ->with('success', 'Loan account created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create loan account: ' . $e->getMessage()]);
        }
    }

    public function show(LoanAccount $loanAccount): View
    {
        $user = Auth::user();

        if ($loanAccount->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $loanAccount->load(['member', 'loanProduct']);

        $payments = LoanPayment::where('loan_account_id', $loanAccount->id)
            ->with(['processedBy'])
            ->orderBy('payment_date', 'desc')
            ->paginate(20);

        $statistics = $this->loanService->getAccountStatistics($loanAccount->id);
        $schedule = $this->loanService->getPaymentSchedule($loanAccount->id);

        return view('loans.show', compact('loanAccount', 'payments', 'statistics', 'schedule'));
    }

    public function payment(LoanAccount $loanAccount): View
    {
        $user = Auth::user();

        if ($loanAccount->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $nextPayment = $this->loanService->getNextPaymentAmount($loanAccount->id);

        return view('loans.payment', compact('loanAccount', 'nextPayment'));
    }

    public function processPayment(LoanPaymentRequest $request, LoanAccount $loanAccount): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($loanAccount->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $dto = new LoanPaymentDTO(
                loanAccountId: $loanAccount->id,
                amount: $request->amount,
                paymentDate: \Carbon\Carbon::parse($request->payment_date),
                notes: $request->notes,
                processedBy: $user->id
            );

            $payment = $this->loanService->processPayment($dto);

            return redirect()
                ->route('loans.show', $loanAccount)
                ->with('success', 'Payment processed successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to process payment: ' . $e->getMessage()]);
        }
    }

    public function approve(LoanAccount $loanAccount): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($loanAccount->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->loanService->approveLoan($loanAccount->id, $user->id);

            return back()->with('success', 'Loan approved successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to approve loan: ' . $e->getMessage()]);
        }
    }

    public function disburse(LoanAccount $loanAccount): RedirectResponse
    {
        try {
            $user = Auth::user();

            if ($loanAccount->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->loanService->disburseLoan($loanAccount->id, $user->id);

            return back()->with('success', 'Loan disbursed successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to disburse loan: ' . $e->getMessage()]);
        }
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();
        return $this->loanService->exportLoanAccounts($user->cooperative_id, $request->all());
    }
}
