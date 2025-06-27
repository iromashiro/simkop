<?php

namespace App\Services\Financial;

use App\Models\Financial\BalanceSheetAccount;
use App\Models\Financial\FinancialReport;
use App\Models\Cooperative;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BalanceSheetService
{
    public function createBalanceSheet(array $data, int $createdBy): FinancialReport
    {
        return DB::transaction(function () use ($data, $createdBy) {
            try {
                // ✅ CRITICAL FIX: Remove double validation - trust Request validation
                $this->validateBusinessLogic($data);

                // Create or update financial report
                $report = FinancialReport::updateOrCreate(
                    [
                        'cooperative_id' => $data['cooperative_id'],
                        'report_type' => 'balance_sheet',
                        'reporting_year' => $data['reporting_year'],
                    ],
                    [
                        'status' => 'draft',
                        'data' => $data,
                        'notes' => $data['notes'] ?? null,
                        'created_by' => $createdBy,
                    ]
                );

                // Delete existing accounts for this report
                BalanceSheetAccount::where('cooperative_id', $data['cooperative_id'])
                    ->where('reporting_year', $data['reporting_year'])
                    ->delete();

                // Create new accounts
                $this->createAccounts($data['accounts'], $data['cooperative_id'], $data['reporting_year']);

                return $report;
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Error creating balance sheet', [
                    'user_id' => $createdBy,
                    'cooperative_id' => $data['cooperative_id'],
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Gagal membuat laporan posisi keuangan: ' . $e->getMessage());
            }
        });
    }

    public function updateBalanceSheet(FinancialReport $report, array $data): FinancialReport
    {
        return DB::transaction(function () use ($report, $data) {
            try {
                // ✅ CRITICAL FIX: Remove double validation - trust Request validation
                $this->validateBusinessLogic($data);

                // Update report
                $report->update([
                    'data' => $data,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Delete existing accounts
                BalanceSheetAccount::where('cooperative_id', $report->cooperative_id)
                    ->where('reporting_year', $report->reporting_year)
                    ->delete();

                // Create new accounts
                $this->createAccounts($data['accounts'], $report->cooperative_id, $report->reporting_year);

                return $report;
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Error updating balance sheet', [
                    'report_id' => $report->id,
                    'user_id' => auth()->id(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw new \Exception('Gagal memperbarui laporan posisi keuangan: ' . $e->getMessage());
            }
        });
    }

    // ✅ CRITICAL FIX: Only validate business logic, not form validation
    private function validateBusinessLogic(array $data): void
    {
        // Only validate business rules that can't be validated in Request
        $this->validateReasonableAmounts($data['accounts']);
        // Remove balance equation validation (already validated in Request)
        // Remove unique account codes validation (already validated in Request)
    }

    // Keep reasonable amounts validation as business logic
    private function validateReasonableAmounts(array $accounts): void
    {
        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $account) {
                    $currentAmount = $account['current_year_amount'] ?? 0;
                    $previousAmount = $account['previous_year_amount'] ?? 0;

                    // Check for unrealistic changes (more than 1000% increase)
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
        foreach (['assets', 'liabilities', 'equity'] as $category) {
            if (isset($accounts[$category])) {
                foreach ($accounts[$category] as $accountData) {
                    BalanceSheetAccount::create([
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
            'assets' => 'asset',
            'liabilities' => 'liability',
            'equity' => 'equity',
            default => throw new \InvalidArgumentException("Invalid category: {$category}")
        };
    }

    public function getPreviousYearData(int $cooperativeId, int $year): array
    {
        $accounts = BalanceSheetAccount::select([
            'account_code',
            'account_name',
            'account_category',
            'account_subcategory',
            'current_year_amount',
            'previous_year_amount',
            'note_reference',
            'sort_order'
        ])
            ->byCooperative($cooperativeId)
            ->byYear($year)
            ->ordered()
            ->get()
            ->groupBy('account_category');

        return [
            'assets' => $accounts->get('asset', collect()),
            'liabilities' => $accounts->get('liability', collect()),
            'equity' => $accounts->get('equity', collect()),
        ];
    }

    public function calculateTotals($accounts): array
    {
        $totals = [
            'current_year' => [
                'assets' => 0,
                'liabilities' => 0,
                'equity' => 0,
            ],
            'previous_year' => [
                'assets' => 0,
                'liabilities' => 0,
                'equity' => 0,
            ],
        ];

        foreach (['asset', 'liability', 'equity'] as $category) {
            $categoryAccounts = $accounts->get($category, collect());

            $categoryKey = $category === 'asset' ? 'assets' : ($category === 'liability' ? 'liabilities' : 'equity');

            $totals['current_year'][$categoryKey] = $categoryAccounts->sum('current_year_amount');
            $totals['previous_year'][$categoryKey] = $categoryAccounts->sum('previous_year_amount');
        }

        // Calculate total liabilities + equity
        $totals['current_year']['liabilities_equity'] =
            $totals['current_year']['liabilities'] + $totals['current_year']['equity'];

        $totals['previous_year']['liabilities_equity'] =
            $totals['previous_year']['liabilities'] + $totals['previous_year']['equity'];

        return $totals;
    }

    public function getDefaultAccountStructure(): array
    {
        return [
            'assets' => [
                [
                    'account_code' => '1100',
                    'account_name' => 'Kas',
                    'account_subcategory' => 'current_asset',
                    'sort_order' => 1,
                ],
                [
                    'account_code' => '1200',
                    'account_name' => 'Bank',
                    'account_subcategory' => 'current_asset',
                    'sort_order' => 2,
                ],
                [
                    'account_code' => '1300',
                    'account_name' => 'Piutang Anggota',
                    'account_subcategory' => 'current_asset',
                    'sort_order' => 3,
                ],
                [
                    'account_code' => '1400',
                    'account_name' => 'Persediaan',
                    'account_subcategory' => 'current_asset',
                    'sort_order' => 4,
                ],
                [
                    'account_code' => '1500',
                    'account_name' => 'Peralatan',
                    'account_subcategory' => 'fixed_asset',
                    'sort_order' => 5,
                ],
                [
                    'account_code' => '1600',
                    'account_name' => 'Akumulasi Penyusutan Peralatan',
                    'account_subcategory' => 'fixed_asset',
                    'sort_order' => 6,
                ],
            ],
            'liabilities' => [
                [
                    'account_code' => '2100',
                    'account_name' => 'Hutang Usaha',
                    'account_subcategory' => 'current_liability',
                    'sort_order' => 1,
                ],
                [
                    'account_code' => '2200',
                    'account_name' => 'Hutang Bank',
                    'account_subcategory' => 'long_term_liability',
                    'sort_order' => 2,
                ],
            ],
            'equity' => [
                [
                    'account_code' => '3100',
                    'account_name' => 'Simpanan Pokok',
                    'account_subcategory' => 'member_equity',
                    'sort_order' => 1,
                ],
                [
                    'account_code' => '3200',
                    'account_name' => 'Simpanan Wajib',
                    'account_subcategory' => 'member_equity',
                    'sort_order' => 2,
                ],
                [
                    'account_code' => '3300',
                    'account_name' => 'Cadangan Umum',
                    'account_subcategory' => 'other_equity',
                    'sort_order' => 3,
                ],
                [
                    'account_code' => '3400',
                    'account_name' => 'Sisa Hasil Usaha',
                    'account_subcategory' => 'retained_earnings',
                    'sort_order' => 4,
                ],
            ],
        ];
    }

    // Keep these methods for backward compatibility and analysis features
    public function validateBalanceEquation(array $accounts): array
    {
        $totalAssets = collect($accounts['assets'] ?? [])->sum('current_year_amount');
        $totalLiabilities = collect($accounts['liabilities'] ?? [])->sum('current_year_amount');
        $totalEquity = collect($accounts['equity'] ?? [])->sum('current_year_amount');

        $difference = $totalAssets - ($totalLiabilities + $totalEquity);

        return [
            'is_balanced' => abs($difference) <= 0.01,
            'difference' => $difference,
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_equity' => $totalLiabilities + $totalEquity,
            ],
        ];
    }

    public function getFinancialRatios(int $cooperativeId, int $year): array
    {
        $accounts = BalanceSheetAccount::byCooperative($cooperativeId)
            ->byYear($year)
            ->get()
            ->groupBy('account_category');

        $currentAssets = $accounts->get('asset', collect())
            ->where('account_subcategory', 'current_asset')
            ->sum('current_year_amount');

        $currentLiabilities = $accounts->get('liability', collect())
            ->where('account_subcategory', 'current_liability')
            ->sum('current_year_amount');

        $totalAssets = $accounts->get('asset', collect())->sum('current_year_amount');
        $totalEquity = $accounts->get('equity', collect())->sum('current_year_amount');

        return [
            'current_ratio' => $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0,
            'debt_to_equity_ratio' => $totalEquity > 0 ? ($totalAssets - $totalEquity) / $totalEquity : 0,
            'equity_ratio' => $totalAssets > 0 ? $totalEquity / $totalAssets : 0,
        ];
    }

    public function getYearOverYearAnalysis(int $cooperativeId, int $year): array
    {
        $currentYear = BalanceSheetAccount::byCooperative($cooperativeId)
            ->byYear($year)
            ->get()
            ->groupBy('account_category');

        $previousYear = BalanceSheetAccount::byCooperative($cooperativeId)
            ->byYear($year - 1)
            ->get()
            ->groupBy('account_category');

        $analysis = [];

        foreach (['asset', 'liability', 'equity'] as $category) {
            $currentTotal = $currentYear->get($category, collect())->sum('current_year_amount');
            $previousTotal = $previousYear->get($category, collect())->sum('current_year_amount');

            $variance = $currentTotal - $previousTotal;
            $variancePercentage = $previousTotal > 0 ? ($variance / $previousTotal) * 100 : 0;

            $analysis[$category] = [
                'current_year' => $currentTotal,
                'previous_year' => $previousTotal,
                'variance' => $variance,
                'variance_percentage' => $variancePercentage,
            ];
        }

        return $analysis;
    }
}
