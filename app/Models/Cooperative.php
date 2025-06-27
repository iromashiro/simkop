<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasAuditLog;

class Cooperative extends Model
{
    use HasFactory, SoftDeletes, HasAuditLog;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'chairman_name',
        'registration_number',
        'status',
        'logo_path',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function financialReports()
    {
        return $this->hasMany(FinancialReport::class);
    }

    public function balanceSheetAccounts()
    {
        return $this->hasMany(BalanceSheetAccount::class);
    }

    public function incomeStatementAccounts()
    {
        return $this->hasMany(IncomeStatementAccount::class);
    }

    public function equityChanges()
    {
        return $this->hasMany(EquityChange::class);
    }

    public function cashFlowActivities()
    {
        return $this->hasMany(CashFlowActivity::class);
    }

    public function memberSavings()
    {
        return $this->hasMany(MemberSaving::class);
    }

    public function memberReceivables()
    {
        return $this->hasMany(MemberReceivable::class);
    }

    public function nonPerformingReceivables()
    {
        return $this->hasMany(NonPerformingReceivable::class);
    }

    public function shuDistribution()
    {
        return $this->hasMany(SHUDistribution::class);
    }

    public function budgetPlans()
    {
        return $this->hasMany(BudgetPlan::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getLogoUrl(): ?string
    {
        return $this->logo_path ? asset('storage/' . $this->logo_path) : null;
    }

    public function getLatestReportYear(): ?int
    {
        return $this->financialReports()->max('reporting_year');
    }

    public function hasReportForYear(int $year, string $reportType = null): bool
    {
        $query = $this->financialReports()->where('reporting_year', $year);

        if ($reportType) {
            $query->where('report_type', $reportType);
        }

        return $query->exists();
    }

    public function getReportStatusForYear(int $year): array
    {
        $reports = $this->financialReports()
            ->where('reporting_year', $year)
            ->get()
            ->keyBy('report_type');

        $reportTypes = [
            'balance_sheet',
            'income_statement',
            'equity_changes',
            'cash_flow',
            'member_savings',
            'member_receivables',
            'npl_receivables',
            'shu_distribution',
            'budget_plan',
            'notes_to_financial'
        ];

        $status = [];
        foreach ($reportTypes as $type) {
            $status[$type] = $reports->get($type)?->status ?? 'not_started';
        }

        return $status;
    }
}
