<?php
// app/Services/Financial/IncomeStatementService.php

namespace App\Services\Financial;

use App\Models\Financial\IncomeStatementAccount;
use App\Models\Financial\FinancialReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class IncomeStatementService
{
    public function createIncomeStatement(array $data, int $createdBy): FinancialReport
    {
        return DB::transaction(function () use ($data, $createdBy) {
            try {
                $this->validateBusinessLogic($data);

                $report = FinancialReport::updateOrCreate(
                    [
                        'cooperative_id' => $data['cooperative_id'],
                        'report_type' => 'income_statement',
                        'reporting_year' => $data['reporting_year'],
                    ],
                    [
                        'status' => 'draft',
                        'data' => $data,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $createdBy,
                    ]
                );

                // Delete existing accounts
                IncomeStatementAccount::where('cooperative_id', $data['cooperative_id'])
                    ->where('reporting_year', $data['reporting_year'])
                    ->delete();

                // Create new accounts
                $this->createAccounts($data['accounts'], $data['cooperative_id'], $data['reporting_year']);

                return $report;
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Error creating income statement', [
                    'user_id' => $createdBy,
                    'cooperative_id' => $data['cooperative_id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Gagal membuat laporan laba rugi: ' . $e->getMessage());
            }
        });
    }

    public function updateIncomeStatement(FinancialReport $report, array $data): FinancialReport
    {
        return DB::transaction(function () use ($report, $data) {
            try {
                $this->validateBusinessLogic($data);

                $report->update([
                    'data' => $data,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Delete existing accounts
                IncomeStatementAccount::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->delete();

                // Create new accounts
                $this->createAccounts($data['accounts'], $report->cooperative_id, $report->reporting_year);

                return $report;
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Error updating income statement', [
                    'report_id' => $report->id,
                    'user_id' => auth()->id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Gagal memperbarui laporan laba rugi: ' . $e->getMessage());
            }
        });
    }

    private function validateBusinessLogic(array $data): void
    {
        $this->validateReasonableAmounts($data['accounts']);
    }

    private function validateReasonableAmounts(array $accounts): void
    {
        foreach (['revenues', 'expenses'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $account) {
                    $currentAmount = $account['current_year_amount'] ?? 0;
                    $previousAmount = $account['previous_year_amount'] ?? 0;

                    if ($previousAmount > 0 && $currentAmount > ($previousAmount * 10)) {
                        throw ValidationException::withMessages([
                            'unrealistic_change' => "Perubahan jumlah terlalu besar untuk akun {$account['account_name']}. Mohon periksa kembali."
                        ]);
                    }
                }
            }
        }
    }

    private function createAccounts(array $accounts, int $cooperativeId, int $reportingYear): void
    {
        foreach (['revenues', 'expenses'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $accountData) {
                    IncomeStatementAccount::create([
                        'cooperative_id' => $cooperativeId,
                        'reporting_year' => $reportingYear,
                        'account_code' => $accountData['account_code'],
                        'account_name' => $accountData['account_name'],
                        'account_category' => $this->mapCategory($category),
                        'account_subcategory' => $accountData['account_subcategory'],
                        'current_year_amount' => $accountData['current_year_amount'],
                        'previous_year_amount' => $accountData['previous_year_amount'] ?? 0,
                        'note_reference' => $accountData['note_reference'] ?? null,
                        'sort_order' => $accountData['sort_order'] ?? 0,
                    ]);
                }
            }
        }
    }

    private function mapCategory(string $category): string
    {
        return match ($category) {
            'revenues' => 'revenue',
            'expenses' => 'expense',
            default => throw new \InvalidArgumentException("Invalid category: {$category}")
        };
    }

    public function calculateTotals($accounts): array
    {
        $totals = [
            'current_year' => [
                'revenues' => 0,
                'expenses' => 0,
                'net_income' => 0,
            ],
            'previous_year' => [
                'revenues' => 0,
                'expenses' => 0,
                'net_income' => 0,
            ],
        ];

        foreach (['revenue', 'expense'] as $category) {
            $categoryAccounts = $accounts->get($category, collect());
            $categoryKey = $category === 'revenue' ? 'revenues' : 'expenses';

            $totals['current_year'][$categoryKey] = $categoryAccounts->sum('current_year_amount');
            $totals['previous_year'][$categoryKey] = $categoryAccounts->sum('previous_year_amount');
        }

        // Calculate net income
        $totals['current_year']['net_income'] =
            $totals['current_year']['revenues'] - $totals['current_year']['expenses'];

        $totals['previous_year']['net_income'] =
            $totals['previous_year']['revenues'] - $totals['previous_year']['expenses'];

        return $totals;
    }
}
