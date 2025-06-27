<?php

namespace App\Exports;

use App\Domain\Reporting\DTOs\ReportParametersDTO;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ReportExport implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(
        private array $reportData,
        private ReportParametersDTO $parameters
    ) {}

    public function array(): array
    {
        return match ($this->parameters->reportType) {
            'financial_statement' => $this->formatFinancialData(),
            'member_report' => $this->formatMemberData(),
            'loan_report' => $this->formatLoanData(),
            'savings_report' => $this->formatSavingsData(),
            'comprehensive_report' => $this->formatComprehensiveData(),
            default => [],
        };
    }

    public function headings(): array
    {
        return match ($this->parameters->reportType) {
            'financial_statement' => [
                'Tanggal',
                'Kode Akun',
                'Nama Akun',
                'Deskripsi',
                'Debit',
                'Kredit'
            ],
            'member_report' => [
                'ID Anggota',
                'Nama',
                'Email',
                'Telepon',
                'Status',
                'Tanggal Bergabung'
            ],
            'loan_report' => [
                'ID Pinjaman',
                'Nama Anggota',
                'Jumlah',
                'Bunga',
                'Status',
                'Tanggal Pinjaman'
            ],
            'savings_report' => [
                'ID Simpanan',
                'Nama Anggota',
                'Jenis',
                'Saldo',
                'Status',
                'Tanggal Buka'
            ],
            default => ['Data'],
        };
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return match ($this->parameters->reportType) {
            'financial_statement' => 'Laporan Keuangan',
            'member_report' => 'Laporan Anggota',
            'loan_report' => 'Laporan Pinjaman',
            'savings_report' => 'Laporan Simpanan',
            'comprehensive_report' => 'Laporan Komprehensif',
            default => 'Laporan',
        };
    }

    private function formatFinancialData(): array
    {
        if (!isset($this->reportData['journal_entries'])) {
            return [];
        }

        return collect($this->reportData['journal_entries'])->map(function ($entry) {
            return [
                $entry->transaction_date,
                $entry->account->code ?? '',
                $entry->account->name ?? '',
                $entry->description,
                $entry->debit_amount,
                $entry->credit_amount,
            ];
        })->toArray();
    }

    private function formatMemberData(): array
    {
        if (!isset($this->reportData['members'])) {
            return [];
        }

        return collect($this->reportData['members'])->map(function ($member) {
            return [
                $member->member_number,
                $member->name,
                $member->email,
                $member->phone,
                $member->status,
                $member->created_at->format('Y-m-d'),
            ];
        })->toArray();
    }

    private function formatLoanData(): array
    {
        if (!isset($this->reportData['loans'])) {
            return [];
        }

        return collect($this->reportData['loans'])->map(function ($loan) {
            return [
                $loan->loan_number,
                $loan->member->name ?? '',
                $loan->amount,
                $loan->interest_rate,
                $loan->status,
                $loan->created_at->format('Y-m-d'),
            ];
        })->toArray();
    }

    private function formatSavingsData(): array
    {
        if (!isset($this->reportData['savings'])) {
            return [];
        }

        return collect($this->reportData['savings'])->map(function ($saving) {
            return [
                $saving->account_number,
                $saving->member->name ?? '',
                $saving->type,
                $saving->balance,
                $saving->status,
                $saving->created_at->format('Y-m-d'),
            ];
        })->toArray();
    }

    private function formatComprehensiveData(): array
    {
        // For comprehensive report, we'll create a summary
        return [
            ['RINGKASAN LAPORAN KOMPREHENSIF'],
            [''],
            ['Anggota Total:', $this->reportData['members']['total_members'] ?? 0],
            ['Anggota Aktif:', $this->reportData['members']['active_members'] ?? 0],
            [''],
            ['Total Pinjaman:', $this->reportData['loans']['total_loans'] ?? 0],
            ['Jumlah Pinjaman:', number_format($this->reportData['loans']['total_amount'] ?? 0, 0, ',', '.')],
            [''],
            ['Total Simpanan:', $this->reportData['savings']['total_accounts'] ?? 0],
            ['Saldo Simpanan:', number_format($this->reportData['savings']['total_balance'] ?? 0, 0, ',', '.')],
        ];
    }
}
