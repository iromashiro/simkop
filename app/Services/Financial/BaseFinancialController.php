<?php
// app/Http/Controllers/Financial/BaseFinancialController.php

namespace App\Http\Controllers\Financial;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

abstract class BaseFinancialController extends Controller
{
    protected string $reportType;
    protected string $viewPrefix;
    protected string $routePrefix;
    protected $service;

    public function __construct(
        protected AuditLogService $auditLogService,
        protected NotificationService $notificationService
    ) {
        $this->middleware('auth');
        $this->middleware('role:admin_koperasi|admin_dinas');
        $this->middleware('cooperative.access')->except(['index']);
    }

    public function index(Request $request): View
    {
        try {
            $search = $request->get('search');
            $year = $request->get('year');
            $status = $request->get('status');
            $perPage = min($request->get('per_page', 15), 50);

            $query = $this->getBaseQuery()
                ->with(['cooperative:id,name'])
                ->where('report_type', $this->reportType);

            // Apply filters
            if ($search) {
                $sanitizedSearch = str_replace(['%', '_'], ['\%', '\_'], $search);
                $query->whereHas('cooperative', function ($q) use ($sanitizedSearch) {
                    $q->where('name', 'ILIKE', "%{$sanitizedSearch}%");
                });
            }

            if ($year) {
                $query->where('reporting_year', $year);
            }

            if ($status) {
                $query->where('status', $status);
            }

            // Apply cooperative filter for admin_koperasi
            if (auth()->user()->hasRole('admin_koperasi')) {
                $query->where('cooperative_id', auth()->user()->cooperative_id);
            }

            $reports = $query->orderBy('created_at', 'desc')->paginate($perPage);
            $years = $this->getAvailableYears();

            return view("{$this->viewPrefix}.index", compact('reports', 'years', 'search', 'year', 'status'));
        } catch (\Exception $e) {
            Log::error("Error loading {$this->reportType} reports", [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Gagal memuat data laporan. Silakan coba lagi.');
        }
    }

    public function create(): View
    {
        $defaultData = $this->service->getDefaultAccountStructure();
        $previousYearData = $this->service->getPreviousYearData(
            auth()->user()->cooperative_id,
            now()->year - 1
        );

        return view("{$this->viewPrefix}.create", compact('defaultData', 'previousYearData'));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            return DB::transaction(function () use ($request) {
                $report = $this->service->{"create" . ucfirst(str_replace('_', '', $this->reportType))}(
                    $request->validated(),
                    auth()->id()
                );

                $this->auditLogService->log(
                    "{$this->reportType}_created",
                    "Laporan {$this->reportType} dibuat",
                    [
                        'report_id' => $report->id,
                        'cooperative_id' => $report->cooperative_id,
                        'reporting_year' => $report->reporting_year
                    ]
                );

                return redirect()->route("{$this->routePrefix}.index")
                    ->with('success', 'Laporan berhasil dibuat.');
            });
        } catch (\Exception $e) {
            Log::error("Error creating {$this->reportType}", [
                'user_id' => auth()->id(),
                'cooperative_id' => auth()->user()->cooperative_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat laporan: ' . $e->getMessage());
        }
    }

    // Abstract methods to be implemented by child controllers
    abstract protected function getBaseQuery();
    abstract protected function getAvailableYears(): array;
}
