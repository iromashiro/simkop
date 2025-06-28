<?php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\NPLReceivablesRequest;
use App\Models\Financial\NonPerformingReceivable;
use App\Models\Financial\FinancialReport;
use App\Services\Financial\NPLReceivablesService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class NPLReceivablesController extends Controller
{
    public function __construct(
        private NPLReceivablesService $nplReceivablesService,
        private NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('can:view_npl_receivables')->only(['index', 'show']);
        $this->middleware('can:create_npl_receivables')->only(['create', 'store']);
        $this->middleware('can:edit_npl_receivables')->only(['edit', 'update']);
        $this->middleware('can:delete_npl_receivables')->only(['destroy']);
        $this->middleware('throttle:financial-reports')->only(['store', 'update', 'submit']);
    }

    public function index(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));
            $search = $request->get('search');
            $perPage = min($request->get('per_page', 15), 50);

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk melihat laporan.');
                }

                $receivables = NonPerformingReceivable::where('cooperative_id', $cooperativeId)
                    ->where('reporting_year', $year)
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('member_name', 'ILIKE', "%{$search}%")
                                ->orWhere('member_number', 'ILIKE', "%{$search}%");
                        });
                    })
                    ->orderBy('overdue_days', 'desc')
                    ->orderBy('member_name')
                    ->paginate($perPage);

                $report = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'npl_receivables')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $receivables = NonPerformingReceivable::forCurrentUser()
                    ->where('reporting_year', $year)
                    ->when($search, function ($query, $search) {
                        $query->where(function ($q) use ($search) {
                            $q->where('member_name', 'ILIKE', "%{$search}%")
                                ->orWhere('member_number', 'ILIKE', "%{$search}%");
                        });
                    })
                    ->orderBy('overdue_days', 'desc')
                    ->orderBy('member_name')
                    ->paginate($perPage);

                $report = FinancialReport::forCurrentUser()
                    ->where('report_type', 'npl_receivables')
                    ->where('reporting_year', $year)
                    ->first();
            }

            $summary = $this->nplReceivablesService->getSummary($cooperativeId, $year);

            return view('financial.npl-receivables.index', compact(
                'receivables',
                'report',
                'cooperativeId',
                'year',
                'search',
                'summary'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading NPL receivables index', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('dashboard')
                ->with('error', 'Terjadi kesalahan saat memuat data piutang tidak lancar.');
        }
    }

    public function create(Request $request)
    {
        try {
            $year = $request->get('year', date('Y'));

            if (auth()->user()->isAdminDinas()) {
                $cooperativeId = $request->get('cooperative_id');
                if (!$cooperativeId) {
                    return redirect()->route('admin.cooperatives.index')
                        ->with('info', 'Pilih koperasi untuk membuat laporan.');
                }

                $existingReport = FinancialReport::where('cooperative_id', $cooperativeId)
                    ->where('report_type', 'npl_receivables')
                    ->where('reporting_year', $year)
                    ->first();
            } else {
                $cooperativeId = auth()->user()->cooperative_id;

                $existingReport = FinancialReport::forCurrentUser()
                    ->where('report_type', 'npl_receivables')
                    ->where('reporting_year', $year)
                    ->first();
            }

            if ($existingReport && !$existingReport->canBeEdited()) {
                return redirect()->route('financial.npl-receivables.index')
                    ->with('error', 'Laporan sudah ada dan tidak dapat diedit.');
            }

            $previousYearData = $this->nplReceivablesService->getPreviousYearData($cooperativeId, $year - 1);

            return view('financial.npl-receivables.create', compact(
                'cooperativeId',
                'year',
                'previousYearData'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading NPL receivables create form', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.npl-receivables.index')
                ->with('error', 'Terjadi kesalahan saat memuat form piutang tidak lancar.');
        }
    }

    public function store(NPLReceivablesRequest $request)
    {
        try {
            $report = $this->nplReceivablesService->createNPLReceivables(
                $request->validated(),
                auth()->id()
            );

            return redirect()->route('financial.npl-receivables.index', [
                'cooperative_id' => $report->cooperative_id,
                'year' => $report->reporting_year
            ])->with('success', 'Daftar Piutang Tidak Lancar berhasil disimpan.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in NPL receivables creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'sql' => $e->getSql(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in NPL receivables creation', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->validated()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function show(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            $receivables = NonPerformingReceivable::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('overdue_days', 'desc')
                ->orderBy('member_name')
                ->get();

            $summary = $this->nplReceivablesService->getSummary(
                $report->cooperative_id,
                $report->reporting_year
            );

            return view('financial.npl-receivables.show', compact(
                'report',
                'receivables',
                'summary'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading NPL receivables show', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.npl-receivables.index')
                ->with('error', 'Terjadi kesalahan saat memuat detail laporan.');
        }
    }

    public function edit(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.npl-receivables.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $receivables = NonPerformingReceivable::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->orderBy('overdue_days', 'desc')
                ->orderBy('member_name')
                ->get();

            return view('financial.npl-receivables.edit', compact(
                'report',
                'receivables'
            ));
        } catch (\Exception $e) {
            Log::error('Error loading NPL receivables edit form', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('financial.npl-receivables.show', $report)
                ->with('error', 'Terjadi kesalahan saat memuat form edit laporan.');
        }
    }

    public function update(NPLReceivablesRequest $request, FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeEdited()) {
                return redirect()->route('financial.npl-receivables.show', $report)
                    ->with('error', 'Laporan tidak dapat diedit.');
            }

            $this->nplReceivablesService->updateNPLReceivables(
                $report,
                $request->validated()
            );

            return redirect()->route('financial.npl-receivables.show', $report)
                ->with('success', 'Daftar Piutang Tidak Lancar berhasil diperbarui.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (QueryException $e) {
            Log::error('Database error in NPL receivables update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'sql' => $e->getSql()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan database. Silakan coba lagi atau hubungi administrator.');
        } catch (\Exception $e) {
            Log::error('Unexpected error in NPL receivables update', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()->withInput()
                ->with('error', 'Terjadi kesalahan sistem. Tim teknis telah diberitahu.');
        }
    }

    public function submit(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->canBeSubmitted()) {
                return back()->with('error', 'Laporan tidak dapat diajukan.');
            }

            $report->submit();

            Event::dispatch('financial.report.submitted', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'submittedBy' => auth()->id(),
                'submittedAt' => now(),
            ]);

            $this->notificationService->reportSubmitted(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil diajukan untuk persetujuan.');
        } catch (\Exception $e) {
            Log::error('Error submitting NPL receivables', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal mengajukan laporan. Silakan coba lagi.');
        }
    }

    public function approve(Request $request, FinancialReport $report)
    {
        try {
            if (!auth()->user()->isAdminDinas()) {
                abort(403, 'Hanya Admin Dinas yang dapat menyetujui laporan.');
            }

            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat disetujui.');
            }

            $report->approve(auth()->id());

            Event::dispatch('financial.report.approved', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'approvedBy' => auth()->id(),
                'approvedAt' => now(),
            ]);

            $this->notificationService->reportApproved(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year
            );

            return back()->with('success', 'Laporan berhasil disetujui.');
        } catch (\Exception $e) {
            Log::error('Error approving NPL receivables', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menyetujui laporan. Silakan coba lagi.');
        }
    }

    public function reject(Request $request, FinancialReport $report)
    {
        try {
            if (!auth()->user()->isAdminDinas()) {
                abort(403, 'Hanya Admin Dinas yang dapat menolak laporan.');
            }

            if (!$report->canBeApproved()) {
                return back()->with('error', 'Laporan tidak dapat ditolak.');
            }

            $request->validate([
                'rejection_reason' => 'required|string|max:1000'
            ]);

            $report->reject(auth()->id(), $request->rejection_reason);

            Event::dispatch('financial.report.rejected', (object) [
                'cooperativeId' => $report->cooperative_id,
                'reportType' => $report->report_type,
                'reportingYear' => $report->reporting_year,
                'rejectionReason' => $request->rejection_reason,
                'rejectedBy' => auth()->id(),
                'rejectedAt' => now(),
            ]);

            $this->notificationService->reportRejected(
                $report->cooperative_id,
                $report->report_type,
                $report->reporting_year,
                $request->rejection_reason
            );

            return back()->with('success', 'Laporan berhasil ditolak.');
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Exception $e) {
            Log::error('Error rejecting NPL receivables', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menolak laporan. Silakan coba lagi.');
        }
    }

    public function destroy(FinancialReport $report)
    {
        try {
            if (!$report->canBeAccessedByCurrentUser()) {
                abort(403, 'Anda tidak memiliki akses ke laporan ini.');
            }

            if (!$report->isDraft()) {
                return back()->with('error', 'Hanya laporan draft yang dapat dihapus.');
            }

            NonPerformingReceivable::where('cooperative_id', $report->cooperative_id)
                ->where('reporting_year', $report->reporting_year)
                ->delete();

            $report->delete();

            return redirect()->route('financial.npl-receivables.index')
                ->with('success', 'Laporan berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Error deleting NPL receivables', [
                'user_id' => auth()->id(),
                'report_id' => $report->id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Gagal menghapus laporan. Silakan coba lagi.');
        }
    }
}
