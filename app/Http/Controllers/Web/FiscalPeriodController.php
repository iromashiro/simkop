<?php
// app/Http/Controllers/Web/FiscalPeriodController.php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Domain\Accounting\Services\FiscalPeriodService;
use App\Domain\Accounting\Models\FiscalPeriod;
use App\Domain\Accounting\DTOs\CreateFiscalPeriodDTO;
use App\Http\Requests\Web\FiscalPeriod\CreateFiscalPeriodRequest;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class FiscalPeriodController extends Controller
{
    public function __construct(
        private readonly FiscalPeriodService $fiscalPeriodService
    ) {
        $this->middleware('auth');
        $this->middleware('tenant.scope');
        $this->middleware('permission:manage_fiscal_periods');
    }

    /**
     * Display fiscal periods
     */
    public function index(): View
    {
        $user = Auth::user();

        $fiscalPeriods = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->with(['createdBy'])
            ->orderBy('start_date', 'desc')
            ->paginate(20);

        // Get statistics
        $statistics = $this->fiscalPeriodService->getFiscalPeriodStatistics($user->cooperative_id);

        return view('fiscal-periods.index', compact('fiscalPeriods', 'statistics'));
    }

    /**
     * Show create fiscal period form
     */
    public function create(): View
    {
        $user = Auth::user();

        // Get the last fiscal period to suggest next period
        $lastPeriod = FiscalPeriod::where('cooperative_id', $user->cooperative_id)
            ->orderBy('end_date', 'desc')
            ->first();

        return view('fiscal-periods.create', compact('lastPeriod'));
    }

    /**
     * Store new fiscal period
     */
    public function store(CreateFiscalPeriodRequest $request): RedirectResponse
    {
        try {
            $user = Auth::user();

            $dto = new CreateFiscalPeriodDTO(
                cooperativeId: $user->cooperative_id,
                name: $request->name,
                startDate: \Carbon\Carbon::parse($request->start_date),
                endDate: \Carbon\Carbon::parse($request->end_date),
                description: $request->description,
                createdBy: $user->id
            );

            $fiscalPeriod = $this->fiscalPeriodService->createFiscalPeriod($dto);

            return redirect()
                ->route('fiscal-periods.show', $fiscalPeriod)
                ->with('success', 'Fiscal period created successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create fiscal period: ' . $e->getMessage()]);
        }
    }

    /**
     * Display fiscal period details
     */
    public function show(FiscalPeriod $fiscalPeriod): View
    {
        $user = Auth::user();

        // Ensure fiscal period belongs to same cooperative
        if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        $fiscalPeriod->load(['createdBy']);

        // Get period statistics
        $statistics = $this->fiscalPeriodService->getPeriodStatistics($fiscalPeriod->id);

        // Get recent journal entries
        $recentEntries = \App\Domain\Accounting\Models\JournalEntry::where('fiscal_period_id', $fiscalPeriod->id)
            ->with(['createdBy', 'lines.account'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('fiscal-periods.show', compact(
            'fiscalPeriod',
            'statistics',
            'recentEntries'
        ));
    }

    /**
     * Show edit fiscal period form
     */
    public function edit(FiscalPeriod $fiscalPeriod): View
    {
        $user = Auth::user();

        // Ensure fiscal period belongs to same cooperative
        if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        // Check if period can be edited
        if ($fiscalPeriod->status === 'closed') {
            return redirect()
                ->route('fiscal-periods.show', $fiscalPeriod)
                ->withErrors(['error' => 'Closed periods cannot be edited']);
        }

        return view('fiscal-periods.edit', compact('fiscalPeriod'));
    }

    /**
     * Update fiscal period
     */
    public function update(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $user = Auth::user();

            // Ensure fiscal period belongs to same cooperative
            if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            // Check if period can be updated
            if ($fiscalPeriod->status === 'closed') {
                return back()->withErrors(['error' => 'Closed periods cannot be updated']);
            }

            $fiscalPeriod->update([
                'name' => $request->name,
                'description' => $request->description,
            ]);

            return redirect()
                ->route('fiscal-periods.show', $fiscalPeriod)
                ->with('success', 'Fiscal period updated successfully');
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update fiscal period: ' . $e->getMessage()]);
        }
    }

    /**
     * Close fiscal period
     */
    public function close(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure fiscal period belongs to same cooperative
            if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->fiscalPeriodService->closeFiscalPeriod($fiscalPeriod->id, $user->id);

            return back()->with('success', 'Fiscal period closed successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to close fiscal period: ' . $e->getMessage()]);
        }
    }

    /**
     * Reopen fiscal period
     */
    public function reopen(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure fiscal period belongs to same cooperative
            if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->fiscalPeriodService->reopenFiscalPeriod($fiscalPeriod->id, $user->id);

            return back()->with('success', 'Fiscal period reopened successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to reopen fiscal period: ' . $e->getMessage()]);
        }
    }

    /**
     * Delete fiscal period
     */
    public function destroy(FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        try {
            $user = Auth::user();

            // Ensure fiscal period belongs to same cooperative
            if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $this->fiscalPeriodService->deleteFiscalPeriod($fiscalPeriod->id);

            return redirect()
                ->route('fiscal-periods.index')
                ->with('success', 'Fiscal period deleted successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to delete fiscal period: ' . $e->getMessage()]);
        }
    }

    /**
     * Year-end closing
     */
    public function yearEndClosing(FiscalPeriod $fiscalPeriod): View
    {
        $user = Auth::user();

        // Ensure fiscal period belongs to same cooperative
        if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
            abort(404);
        }

        // Get year-end closing data
        $closingData = $this->fiscalPeriodService->getYearEndClosingData($fiscalPeriod->id);

        return view('fiscal-periods.year-end-closing', compact('fiscalPeriod', 'closingData'));
    }

    /**
     * Process year-end closing
     */
    public function processYearEndClosing(Request $request, FiscalPeriod $fiscalPeriod): RedirectResponse
    {
        $request->validate([
            'retained_earnings_account_id' => 'required|integer|exists:accounts,id',
            'closing_date' => 'required|date',
        ]);

        try {
            $user = Auth::user();

            // Ensure fiscal period belongs to same cooperative
            if ($fiscalPeriod->cooperative_id !== $user->cooperative_id) {
                abort(404);
            }

            $result = $this->fiscalPeriodService->processYearEndClosing(
                $fiscalPeriod->id,
                $request->retained_earnings_account_id,
                \Carbon\Carbon::parse($request->closing_date),
                $user->id
            );

            return redirect()
                ->route('fiscal-periods.show', $fiscalPeriod)
                ->with('success', 'Year-end closing completed successfully');
        } catch (\Exception $e) {
            return back()
                ->withErrors(['error' => 'Failed to process year-end closing: ' . $e->getMessage()]);
        }
    }
}
