<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // Cooperative management
            'view_cooperatives',
            'create_cooperatives',
            'edit_cooperatives',
            'delete_cooperatives',

            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // Financial reports
            'view_financial_reports',
            'create_financial_reports',
            'edit_financial_reports',
            'delete_financial_reports',
            'submit_financial_reports',
            'approve_financial_reports',
            'reject_financial_reports',

            // Balance sheet
            'view_balance_sheet',
            'create_balance_sheet',
            'edit_balance_sheet',
            'delete_balance_sheet',

            // Income statement
            'view_income_statement',
            'create_income_statement',
            'edit_income_statement',
            'delete_income_statement',

            // Equity changes
            'view_equity_changes',
            'create_equity_changes',
            'edit_equity_changes',
            'delete_equity_changes',

            // Cash flow
            'view_cash_flow',
            'create_cash_flow',
            'edit_cash_flow',
            'delete_cash_flow',

            // Member savings
            'view_member_savings',
            'create_member_savings',
            'edit_member_savings',
            'delete_member_savings',

            // Member receivables
            'view_member_receivables',
            'create_member_receivables',
            'edit_member_receivables',
            'delete_member_receivables',

            // NPL receivables
            'view_npl_receivables',
            'create_npl_receivables',
            'edit_npl_receivables',
            'delete_npl_receivables',

            // SHU distribution
            'view_shu_distribution',
            'create_shu_distribution',
            'edit_shu_distribution',
            'delete_shu_distribution',

            // Budget plan
            'view_budget_plan',
            'create_budget_plan',
            'edit_budget_plan',
            'delete_budget_plan',

            // Reports export
            'export_reports',
            'export_pdf',
            'export_excel',
            'batch_export',

            // Notifications
            'view_notifications',
            'manage_notifications',

            // Audit logs
            'view_audit_logs',

            // Dashboard
            'view_admin_dashboard',
            'view_cooperative_dashboard',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles
        $adminDinasRole = Role::firstOrCreate(['name' => 'admin_dinas']);
        $adminKoperasiRole = Role::firstOrCreate(['name' => 'admin_koperasi']);

        // Assign permissions to Admin Dinas (all permissions)
        $adminDinasRole->syncPermissions(Permission::all());

        // Assign permissions to Admin Koperasi (limited permissions)
        $adminKoperasiPermissions = [
            // Financial reports (own cooperative only)
            'view_financial_reports',
            'create_financial_reports',
            'edit_financial_reports',
            'submit_financial_reports',

            // Balance sheet
            'view_balance_sheet',
            'create_balance_sheet',
            'edit_balance_sheet',

            // Income statement
            'view_income_statement',
            'create_income_statement',
            'edit_income_statement',

            // Equity changes
            'view_equity_changes',
            'create_equity_changes',
            'edit_equity_changes',

            // Cash flow
            'view_cash_flow',
            'create_cash_flow',
            'edit_cash_flow',

            // Member savings
            'view_member_savings',
            'create_member_savings',
            'edit_member_savings',

            // Member receivables
            'view_member_receivables',
            'create_member_receivables',
            'edit_member_receivables',

            // NPL receivables
            'view_npl_receivables',
            'create_npl_receivables',
            'edit_npl_receivables',

            // SHU distribution
            'view_shu_distribution',
            'create_shu_distribution',
            'edit_shu_distribution',

            // Budget plan
            'view_budget_plan',
            'create_budget_plan',
            'edit_budget_plan',

            // Reports export
            'export_reports',
            'export_pdf',
            'export_excel',

            // Notifications
            'view_notifications',

            // Dashboard
            'view_cooperative_dashboard',
        ];

        $adminKoperasiRole->syncPermissions($adminKoperasiPermissions);
    }
}
