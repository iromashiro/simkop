<?php
// app/Http/Controllers/Web/JournalEntryController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Services\JournalEntryService;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\DTOs\CreateJournalEntryDTO;
use App\Domain\Accounting\DTOs\JournalEntryLineDTO;
use App\Http\Requests\Web\JournalEntry\CreateJournalEntryRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class JournalEntryController extends Controller
{
    public function __construct(
        private readonly JournalEntryService $journalEntryService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_journal_entries');
    }

    /**
     * Display journal entries
     */
    public function index(Request $request): View
    {
        $user = Auth::user();

        $query = JournalEntry::where('cooperative_id', $user->cooperative_id)
            ->with(['fiscalPeriod', 'createdBy', 'lines.account'])
            ->orderBy('entry_date', 'desc')
            ->orderBy('entry_number', 'desc');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('entry_number', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('reference', 'ILIKE', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('entry_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('entry_date', '<=', $request->date_to);
        }

        // Fiscal period filter
        if ($request->filled('fiscal_period_id')) {
            $query->where('fiscal_period_id', $request->fiscal_period_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $journalEntries = $query->paginate(20)->withQueryString();

        // Get fiscal periods for filter
        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        // Get statistics
        $statistics = $this->journalEntryService->getJournalEntryStatistics($user->cooperative_id);

        return view('journal-entries.index', compact(
            'journalEntries',
            'fiscalPeriods',
            'statistics'
        ));
    }

    /**
     * Show create journal entry form
     */
    public function create(): View
    {
        $user = Auth::user();

        // Get active accounts
        $accounts = Account::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->where('is_header', false) // Only detail accounts
            ->orderBy('code')
            ->get();

        // Get current fiscal period
        $currentFiscalPeriod = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->where('status', 'open')
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->first();

        // Get all fiscal periods
        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->where('status', 'open')
            ->orderBy('start_date', 'desc')
            ->get();

        return view('journal-entries.create', compact(
            'accounts',
            'currentFiscalPeriod',
            'fiscalPeriods'
        ));
    }

    /**
     * Store new journal entry
     */
    public function store(CreateJournalEntryRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Prepare journal entry lines
            $lines = collect($request->lines)->map(function ($line) {
                return new JournalEntryLineDTO(
                    accountId: $line['account_id'],
                    description: $line['description'],
                    debitAmount: $line['debit_amount'] ?? 0,
                    creditAmount: $line['credit_amount'] ?? 0
                );
            })->toArray();

            $dto = new CreateJournalEntryDTO(
                cooperativeId: $user->cooperative_id,
                fiscalPeriodId: $request->fiscal_period_id,
                entryDate: \Carbon\Carbon::parse($request->entry_date),
                description: $request->description,
                reference: $request->reference,
                lines: $lines,
                createdBy: $user->id
            );

            $journalEntry = $this->journalEntryService->createJournalEntry($dto);

            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('success', 'Journal entry created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Display journal entry details
     */
    public function show(JournalEntry $journalEntry): View
    {
        $user = Auth::user();

        // Ensure journal entry belongs to same cooperative
        if ($journalEntry->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $journalEntry->load([
            'fiscalPeriod',
            'createdBy',
            'lines.account',
            'approvedBy',
            'reversedBy',
            'reversalEntry'
        ]);

        return view('journal-entries.show', compact('journalEntry'));
    }

    /**
     * Show edit journal entry form
     */
    public function edit(JournalEntry $journalEntry): View
    {
        $user = Auth::user();

        // Ensure journal entry belongs to same cooperative
        if ($journalEntry->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        // Check if entry can be edited
        if ($journalEntry->status !== 'draft') {
            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->withErrors(['error' => 'Only draft entries can be edited']);
        }

        $journalEntry->load(['lines.account']);

        // Get active accounts
        $accounts = Account::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get();

        // Get fiscal periods
        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->where('status', 'open')
            ->orderBy('start_date', 'desc')
            ->get();

        return view('journal-entries.edit', compact(
            'journalEntry',
            'accounts',
            'fiscalPeriods'
        ));
    }

    /**
     * Update journal entry
     */
    public function update(CreateJournalEntryRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure journal entry belongs to same cooperative
            if ($journalEntry->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            // Check if entry can be updated
            if ($journalEntry->status !== 'draft') {
                return back()->withErrors(['error' => 'Only draft entries can be updated']);
            }

            // Prepare journal entry lines
            $lines = collect($request->lines)->map(function ($line) {
                return new JournalEntryLineDTO(
                    accountId: $line['account_id'],
                    description: $line['description'],
                    debitAmount: $line['debit_amount'] ?? 0,
                    creditAmount: $line['credit_amount'] ?? 0
                );
            })->toArray();

            $this->journalEntryService->updateJournalEntry(
                $journalEntry->id,
                $request->description,
                $request->reference,
                $lines
            );

            return redirect()
                ->route('journal-entries.show', $journalEntry)
                ->with('success', 'Journal entry updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete journal entry
     */
    public function destroy(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure journal entry belongs to same cooperative
            if ($journalEntry->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->journalEntryService->deleteJournalEntry($journalEntry->id);

            return redirect()
                ->route('journal-entries.index')
                ->with('success', 'Journal entry deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Approve journal entry
     */
    public function approve(JournalEntry $journalEntry): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure journal entry belongs to same cooperative
            if ($journalEntry->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->journalEntryService->approveJournalEntry($journalEntry->id, $user->id);

            return back()->with('success', 'Journal entry approved successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to approve journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Reverse journal entry
     */
    public function reverse(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $user = Auth::user();

            // Ensure journal entry belongs to same cooperative
            if ($journalEntry->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $reversalEntry = $this->journalEntryService->reverseJournalEntry(
                $journalEntry->id,
                $request->reason,
                $user->id
            );

            return redirect()
                ->route('journal-entries.show', $reversalEntry)
                ->with('success', 'Journal entry reversed successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to reverse journal entry: ' . $e->getMessage()]);
        }
    }

    /**
     * Show general ledger
     */
    public function generalLedger(Request $request): View
    {
        $request->validate([
            'account_id' => 'integer|exists:accounts,id',
            'date_from' => 'date',
            'date_to' => 'date',
            'fiscal_period_id' => 'integer|exists:fiscal_periods,id',
        ]);

        $user = Auth::user();

        $generalLedger = $this->journalEntryService->getGeneralLedger(
            $user->cooperative_id,
            $request->account_id,
            $request->date_from ? \Carbon\Carbon::parse($request->date_from) : null,
            $request->date_to ? \Carbon\Carbon::parse($request->date_to) : null,
            $request->fiscal_period_id
        );

        // Get accounts for filter
        $accounts = Account::where('cooperative_id', $user->cooperative_id)
            ->where('is_active', true)
            ->where('is_header', false)
            ->orderBy('code')
            ->get();

        // Get fiscal periods for filter
        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('start_date', 'desc')
            ->get();

        return view('journal-entries.general-ledger', compact(
            'generalLedger',
            'accounts',
            'fiscalPeriods'
        ));
    }

    /**
     * Export journal entries
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $user = Auth::user();

        return $this->journalEntryService->exportJournalEntries(
            $user->cooperative_id,
            $request->all()
        );
    }
}
