<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            // Cooperative management
            'view_cooperatives',
            'create_cooperatives',
            'edit_cooperatives',
            'delete_cooperatives',

            // Member management
            'view_members',
            'create_members',
            'edit_members',
            'delete_members',

            // Savings management
            'view_savings',
            'create_savings',
            'edit_savings',
            'delete_savings',
            'process_savings_transactions',

            // Loan management
            'view_loans',
            'create_loans',
            'edit_loans',
            'delete_loans',
            'approve_loans',
            'process_loan_payments',

            // Accounting
            'view_accounts',
            'create_accounts',
            'edit_accounts',
            'delete_accounts',
            'view_journal_entries',
            'create_journal_entries',
            'edit_journal_entries',
            'delete_journal_entries',

            // Reports
            'view_reports',
            'generate_reports',
            'export_reports',

            // SHU Management
            'view_shu',
            'create_shu_plans',
            'edit_shu_plans',
            'approve_shu_distribution',

            // Budget Management
            'view_budgets',
            'create_budgets',
            'edit_budgets',
            'approve_budgets',

            // User management
            'view_users',
            'create_users',
            'edit_users',
            'delete_users',

            // System settings
            'view_settings',
            'edit_settings',
            'view_audit_logs',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create roles and assign permissions

        // Super Admin (Dinas Koperasi)
        $superAdmin = Role::create(['name' => 'super_admin']);
        $superAdmin->givePermissionTo(Permission::all());

        // Cooperative Admin
        $cooperativeAdmin = Role::create(['name' => 'cooperative_admin']);
        $cooperativeAdmin->givePermissionTo([
            'view_members',
            'create_members',
            'edit_members',
            'delete_members',
            'view_savings',
            'create_savings',
            'edit_savings',
            'process_savings_transactions',
            'view_loans',
            'create_loans',
            'edit_loans',
            'approve_loans',
            'process_loan_payments',
            'view_accounts',
            'create_accounts',
            'edit_accounts',
            'view_journal_entries',
            'create_journal_entries',
            'edit_journal_entries',
            'view_reports',
            'generate_reports',
            'export_reports',
            'view_shu',
            'create_shu_plans',
            'edit_shu_plans',
            'view_budgets',
            'create_budgets',
            'edit_budgets',
            'view_users',
            'create_users',
            'edit_users',
            'view_settings',
            'edit_settings',
        ]);

        // Cooperative Staff
        $cooperativeStaff = Role::create(['name' => 'cooperative_staff']);
        $cooperativeStaff->givePermissionTo([
            'view_members',
            'create_members',
            'edit_members',
            'view_savings',
            'process_savings_transactions',
            'view_loans',
            'process_loan_payments',
            'view_accounts',
            'view_journal_entries',
            'create_journal_entries',
            'view_reports',
            'generate_reports',
            'view_shu',
            'view_budgets',
        ]);

        // Member (Anggota Koperasi)
        $member = Role::create(['name' => 'member']);
        $member->givePermissionTo([
            'view_savings', // Hanya simpanan sendiri
            'view_loans',   // Hanya pinjaman sendiri
            'view_reports', // Laporan terbatas
        ]);
    }
}
