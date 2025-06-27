<?php

namespace App\Domain\Reporting\Services;

use App\Domain\Reporting\DTOs\ReportParametersDTO;
use App\Domain\Cooperative\Models\Cooperative;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Member\Models\Member;
use App\Domain\Loan\Models\Loan;
use App\Domain\Savings\Models\Savings;
use App\Mail\AutomatedReportMail;
use App\Exports\ReportExport;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportGenerationService
{
    public function generateReport(ReportParametersDTO $parameters): array
    {
        try {
            Log::info('Starting report generation', $parameters->toArray());

            $cooperative = Cooperative::findOrFail($parameters->cooperativeId);

            $reportData = $this->collectReportData($parameters, $cooperative);

            $filePath = $this->generateReportFile($parameters, $reportData);

            if ($parameters->isEmailReport()) {
                $this->sendReportByEmail($parameters, $filePath, $cooperative);
            }

            Log::info('Report generation completed', ['file_path' => $filePath]);

            return [
                'success' => true,
                'file_path' => $filePath,
                'report_type' => $parameters->reportType,
                'format' => $parameters->format,
                'generated_at' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Report generation failed', [
                'error' => $e->getMessage(),
                'parameters' => $parameters->toArray(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'parameters' => $parameters->toArray(),
            ];
        }
    }

    private function collectReportData(ReportParametersDTO $parameters, Cooperative $cooperative): array
    {
        $startDate = Carbon::parse($parameters->startDate);
        $endDate = Carbon::parse($parameters->endDate);

        return match ($parameters->reportType) {
            'financial_statement' => $this->getFinancialStatementData($cooperative->id, $startDate, $endDate),
            'member_report' => $this->getMemberReportData($cooperative->id, $startDate, $endDate),
            'loan_report' => $this->getLoanReportData($cooperative->id, $startDate, $endDate),
            'savings_report' => $this->getSavingsReportData($cooperative->id, $startDate, $endDate),
            'comprehensive_report' => $this->getComprehensiveReportData($cooperative->id, $startDate, $endDate),
            default => throw new \InvalidArgumentException("Unsupported report type: {$parameters->reportType}")
        };
    }

    private function getFinancialStatementData(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $journalEntries = JournalEntry::where('cooperative_id', $cooperativeId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->with(['account'])
            ->get();

        return [
            'title' => 'Laporan Keuangan',
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'journal_entries' => $journalEntries,
            'total_debit' => $journalEntries->sum('debit_amount'),
            'total_credit' => $journalEntries->sum('credit_amount'),
            'entry_count' => $journalEntries->count(),
        ];
    }

    private function getMemberReportData(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $members = Member::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        return [
            'title' => 'Laporan Anggota',
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'members' => $members,
            'total_members' => $members->count(),
            'active_members' => $members->where('status', 'active')->count(),
            'inactive_members' => $members->where('status', 'inactive')->count(),
        ];
    }

    private function getLoanReportData(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $loans = Loan::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['member'])
            ->get();

        return [
            'title' => 'Laporan Pinjaman',
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'loans' => $loans,
            'total_loans' => $loans->count(),
            'total_amount' => $loans->sum('amount'),
            'active_loans' => $loans->where('status', 'active')->count(),
            'completed_loans' => $loans->where('status', 'completed')->count(),
        ];
    }

    private function getSavingsReportData(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        $savings = Savings::where('cooperative_id', $cooperativeId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['member'])
            ->get();

        return [
            'title' => 'Laporan Simpanan',
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'savings' => $savings,
            'total_accounts' => $savings->count(),
            'total_balance' => $savings->sum('balance'),
            'active_accounts' => $savings->where('status', 'active')->count(),
        ];
    }

    private function getComprehensiveReportData(int $cooperativeId, Carbon $startDate, Carbon $endDate): array
    {
        return [
            'title' => 'Laporan Komprehensif',
            'period' => $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y'),
            'financial' => $this->getFinancialStatementData($cooperativeId, $startDate, $endDate),
            'members' => $this->getMemberReportData($cooperativeId, $startDate, $endDate),
            'loans' => $this->getLoanReportData($cooperativeId, $startDate, $endDate),
            'savings' => $this->getSavingsReportData($cooperativeId, $startDate, $endDate),
        ];
    }

    private function generateReportFile(ReportParametersDTO $parameters, array $reportData): string
    {
        $fileName = $this->generateFileName($parameters);

        if ($parameters->isPdfFormat()) {
            return $this->generatePdfReport($fileName, $reportData, $parameters);
        } else {
            return $this->generateExcelReport($fileName, $reportData, $parameters);
        }
    }

    private function generateFileName(ReportParametersDTO $parameters): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        return "reports/{$parameters->reportType}_{$parameters->cooperativeId}_{$timestamp}";
    }

    private function generatePdfReport(string $fileName, array $reportData, ReportParametersDTO $parameters): string
    {
        $pdf = Pdf::loadView('reports.pdf.template', [
            'data' => $reportData,
            'parameters' => $parameters,
        ]);

        $filePath = $fileName . '.pdf';
        Storage::disk('local')->put($filePath, $pdf->output());

        return $filePath;
    }

    private function generateExcelReport(string $fileName, array $reportData, ReportParametersDTO $parameters): string
    {
        $filePath = $fileName . '.xlsx';

        Excel::store(
            new ReportExport($reportData, $parameters),
            $filePath,
            'local'
        );

        return $filePath;
    }

    private function sendReportByEmail(ReportParametersDTO $parameters, string $filePath, Cooperative $cooperative): void
    {
        Mail::to($parameters->email)->send(
            new AutomatedReportMail($parameters, $filePath, $cooperative)
        );
    }

    public function getAvailableReportTypes(): array
    {
        return [
            'financial_statement' => 'Laporan Keuangan',
            'member_report' => 'Laporan Anggota',
            'loan_report' => 'Laporan Pinjaman',
            'savings_report' => 'Laporan Simpanan',
            'comprehensive_report' => 'Laporan Komprehensif',
        ];
    }

    public function getAvailableFormats(): array
    {
        return [
            'pdf' => 'PDF',
            'excel' => 'Excel',
            'csv' => 'CSV',
        ];
    }
}
