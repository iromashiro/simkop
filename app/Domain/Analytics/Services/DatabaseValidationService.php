<?php
// app/Domain/Analytics/Services/DatabaseValidationService.php
namespace App\Domain\Analytics\Services;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Database Structure Validation Service
 * Ensures all required tables and columns exist before analytics operations
 */
class DatabaseValidationService
{
    private array $requiredTables = [
        'savings_accounts' => [
            'id',
            'cooperative_id',
            'member_id',
            'account_number',
            'account_type',
            'balance',
            'status',
            'created_at',
            'updated_at'
        ],
        'savings_transactions' => [
            'id',
            'cooperative_id',
            'savings_account_id',
            'transaction_type',
            'amount',
            'transaction_date',
            'description',
            'created_at'
        ],
        'loan_accounts' => [
            'id',
            'cooperative_id',
            'member_id',
            'loan_type',
            'principal_amount',
            'outstanding_balance',
            'monthly_payment',
            'status',
            'days_past_due'
        ],
        'loan_payments' => [
            'id',
            'loan_account_id',
            'amount',
            'payment_date',
            'payment_type'
        ],
        'accounts' => [
            'id',
            'cooperative_id',
            'account_code',
            'account_name',
            'account_type',
            'account_subtype',
            'balance',
            'is_active',
            'cost_type'
        ],
        'journal_entries' => [
            'id',
            'cooperative_id',
            'entry_date',
            'total_amount',
            'description'
        ],
        'journal_entry_lines' => [
            'id',
            'journal_entry_id',
            'account_id',
            'debit_amount',
            'credit_amount'
        ]
    ];

    /**
     * Validate all required database structures
     */
    public function validateStructure(): array
    {
        $issues = [];

        foreach ($this->requiredTables as $tableName => $columns) {
            // Check if table exists
            if (!Schema::hasTable($tableName)) {
                $issues[] = [
                    'type' => 'missing_table',
                    'table' => $tableName,
                    'severity' => 'critical',
                    'message' => "Required table '{$tableName}' not found"
                ];
                continue;
            }

            // Check if required columns exist
            foreach ($columns as $column) {
                if (!Schema::hasColumn($tableName, $column)) {
                    $issues[] = [
                        'type' => 'missing_column',
                        'table' => $tableName,
                        'column' => $column,
                        'severity' => 'high',
                        'message' => "Required column '{$column}' not found in table '{$tableName}'"
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Validate specific table for analytics provider
     */
    public function validateTableForProvider(string $providerType): bool
    {
        $requiredTables = match ($providerType) {
            'savings_trends' => ['savings_accounts', 'savings_transactions'],
            'loan_portfolio' => ['loan_accounts', 'loan_payments'],
            'financial_overview' => ['accounts', 'journal_entries', 'journal_entry_lines'],
            'profitability' => ['accounts', 'journal_entries'],
            'risk_metrics' => ['loan_accounts', 'savings_accounts', 'accounts'],
            default => []
        };

        foreach ($requiredTables as $table) {
            if (!Schema::hasTable($table)) {
                throw new \Exception("Provider '{$providerType}' requires table '{$table}' which does not exist");
            }
        }

        return true;
    }

    /**
     * Get database health report
     */
    public function getHealthReport(): array
    {
        $issues = $this->validateStructure();

        return [
            'status' => empty($issues) ? 'healthy' : 'issues_found',
            'total_issues' => count($issues),
            'critical_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'critical')),
            'high_issues' => count(array_filter($issues, fn($i) => $i['severity'] === 'high')),
            'issues' => $issues,
            'checked_at' => now()->toISOString()
        ];
    }
}
