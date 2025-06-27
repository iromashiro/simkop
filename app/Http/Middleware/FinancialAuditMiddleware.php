<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\AuditLogService;
use Symfony\Component\HttpFoundation\Response;

class FinancialAuditMiddleware
{
    public function __construct(
        private AuditLogService $auditLogService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log financial operations
        if ($this->isFinancialOperation($request) && auth()->check()) {
            $this->logFinancialActivity($request);
        }

        return $response;
    }

    private function isFinancialOperation(Request $request): bool
    {
        $routeName = $request->route()?->getName() ?? '';

        $financialRoutes = [
            'financial.',
            'reports.',
            'balance-sheet.',
            'income-statement.',
            'equity-changes.',
            'cash-flow.',
            'member-savings.',
            'member-receivables.',
            'npl-receivables.',
            'shu-distribution.',
            'budget-plan.',
        ];

        foreach ($financialRoutes as $route) {
            if (str_contains($routeName, $route)) {
                return true;
            }
        }

        return false;
    }

    private function logFinancialActivity(Request $request): void
    {
        $routeName = $request->route()?->getName() ?? '';
        $method = $request->method();
        $tableName = $this->getTableNameFromRoute($routeName);
        $recordId = $this->getRecordIdFromRequest($request);

        $action = match ($method) {
            'GET' => 'VIEW',
            'POST' => 'CREATE',
            'PUT', 'PATCH' => 'UPDATE',
            'DELETE' => 'DELETE',
            default => $method,
        };

        // Special handling for specific actions
        if (str_contains($routeName, 'export')) {
            $action = 'EXPORT';
        } elseif (str_contains($routeName, 'approve')) {
            $action = 'APPROVE';
        } elseif (str_contains($routeName, 'reject')) {
            $action = 'REJECT';
        }

        try {
            $this->auditLogService->log(
                $tableName,
                $recordId,
                $action,
                $this->getOldValues($request),
                $this->getNewValues($request)
            );
        } catch (\Exception $e) {
            // Log error but don't break the request
            \Log::error('Failed to create audit log: ' . $e->getMessage());
        }
    }

    private function getTableNameFromRoute(string $routeName): string
    {
        if (str_contains($routeName, 'balance-sheet')) return 'balance_sheet_accounts';
        if (str_contains($routeName, 'income-statement')) return 'income_statement_accounts';
        if (str_contains($routeName, 'equity-changes')) return 'equity_changes';
        if (str_contains($routeName, 'cash-flow')) return 'cash_flow_activities';
        if (str_contains($routeName, 'member-savings')) return 'member_savings';
        if (str_contains($routeName, 'member-receivables')) return 'member_receivables';
        if (str_contains($routeName, 'npl-receivables')) return 'non_performing_receivables';
        if (str_contains($routeName, 'shu-distribution')) return 'shu_distribution';
        if (str_contains($routeName, 'budget-plan')) return 'budget_plans';
        if (str_contains($routeName, 'financial')) return 'financial_reports';

        return 'unknown';
    }

    private function getRecordIdFromRequest(Request $request): ?int
    {
        // Try to get ID from route parameters
        $routeParams = $request->route()?->parameters() ?? [];

        foreach (['id', 'report', 'account', 'member', 'budget'] as $param) {
            if (isset($routeParams[$param]) && is_numeric($routeParams[$param])) {
                return (int) $routeParams[$param];
            }
        }

        return null;
    }

    private function getOldValues(Request $request): ?array
    {
        // For UPDATE operations, we would need to fetch the old values
        // This is a simplified implementation
        return null;
    }

    private function getNewValues(Request $request): ?array
    {
        // Return request data for CREATE/UPDATE operations
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            return $request->except(['_token', '_method', 'password', 'password_confirmation']);
        }

        return null;
    }
}
