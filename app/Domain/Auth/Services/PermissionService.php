<?php
// app/Domain/Auth/Services/PermissionService.php
namespace App\Domain\Auth\Services;

use App\Domain\Auth\Models\Permission;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    public function createPermission(
        string $name,
        string $displayName,
        string $description,
        string $group,
        bool $isSystemPermission = false
    ): Permission {
        $permission = Permission::create([
            'name' => $name,
            'display_name' => $displayName,
            'description' => $description,
            'group' => $group,
            'is_system_permission' => $isSystemPermission,
        ]);

        Log::info('Permission created', [
            'permission_id' => $permission->id,
            'name' => $permission->name,
            'group' => $permission->group,
        ]);

        return $permission;
    }

    public function getAllPermissions(): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::orderBy('group')->orderBy('name')->get();
    }

    public function getPermissionsByGroup(string $group): \Illuminate\Database\Eloquent\Collection
    {
        return Permission::byGroup($group)->orderBy('name')->get();
    }

    public function getGroupedPermissions(): array
    {
        return Permission::getGroupedPermissions();
    }

    public function createDefaultPermissions(): array
    {
        $permissions = [
            // Cooperative Management
            ['name' => 'cooperative.view', 'display_name' => 'View Cooperative', 'description' => 'View cooperative information', 'group' => 'cooperative'],
            ['name' => 'cooperative.create', 'display_name' => 'Create Cooperative', 'description' => 'Create new cooperative', 'group' => 'cooperative'],
            ['name' => 'cooperative.update', 'display_name' => 'Update Cooperative', 'description' => 'Update cooperative information', 'group' => 'cooperative'],
            ['name' => 'cooperative.delete', 'display_name' => 'Delete Cooperative', 'description' => 'Delete cooperative', 'group' => 'cooperative'],

            // User Management
            ['name' => 'user.view', 'display_name' => 'View Users', 'description' => 'View user information', 'group' => 'user'],
            ['name' => 'user.create', 'display_name' => 'Create User', 'description' => 'Create new user', 'group' => 'user'],
            ['name' => 'user.update', 'display_name' => 'Update User', 'description' => 'Update user information', 'group' => 'user'],
            ['name' => 'user.delete', 'display_name' => 'Delete User', 'description' => 'Delete user', 'group' => 'user'],

            // Member Management
            ['name' => 'member.view', 'display_name' => 'View Members', 'description' => 'View member information', 'group' => 'member'],
            ['name' => 'member.create', 'display_name' => 'Create Member', 'description' => 'Create new member', 'group' => 'member'],
            ['name' => 'member.update', 'display_name' => 'Update Member', 'description' => 'Update member information', 'group' => 'member'],
            ['name' => 'member.delete', 'display_name' => 'Delete Member', 'description' => 'Delete member', 'group' => 'member'],

            // Financial Management
            ['name' => 'financial.view', 'display_name' => 'View Financial', 'description' => 'View financial information', 'group' => 'financial'],
            ['name' => 'financial.create', 'display_name' => 'Create Transaction', 'description' => 'Create financial transaction', 'group' => 'financial'],
            ['name' => 'financial.update', 'display_name' => 'Update Transaction', 'description' => 'Update financial transaction', 'group' => 'financial'],
            ['name' => 'financial.delete', 'display_name' => 'Delete Transaction', 'description' => 'Delete financial transaction', 'group' => 'financial'],
            ['name' => 'financial.approve', 'display_name' => 'Approve Transaction', 'description' => 'Approve financial transaction', 'group' => 'financial'],

            // Savings Management
            ['name' => 'savings.view', 'display_name' => 'View Savings', 'description' => 'View savings information', 'group' => 'savings'],
            ['name' => 'savings.create', 'display_name' => 'Create Savings', 'description' => 'Create savings transaction', 'group' => 'savings'],
            ['name' => 'savings.update', 'display_name' => 'Update Savings', 'description' => 'Update savings transaction', 'group' => 'savings'],
            ['name' => 'savings.delete', 'display_name' => 'Delete Savings', 'description' => 'Delete savings transaction', 'group' => 'savings'],

            // Loan Management
            ['name' => 'loan.view', 'display_name' => 'View Loans', 'description' => 'View loan information', 'group' => 'loan'],
            ['name' => 'loan.create', 'display_name' => 'Create Loan', 'description' => 'Create loan application', 'group' => 'loan'],
            ['name' => 'loan.update', 'display_name' => 'Update Loan', 'description' => 'Update loan information', 'group' => 'loan'],
            ['name' => 'loan.delete', 'display_name' => 'Delete Loan', 'description' => 'Delete loan', 'group' => 'loan'],
            ['name' => 'loan.approve', 'display_name' => 'Approve Loan', 'description' => 'Approve loan application', 'group' => 'loan'],

            // Report Management
            ['name' => 'report.view', 'display_name' => 'View Reports', 'description' => 'View reports', 'group' => 'report'],
            ['name' => 'report.generate', 'display_name' => 'Generate Reports', 'description' => 'Generate new reports', 'group' => 'report'],
            ['name' => 'report.export', 'display_name' => 'Export Reports', 'description' => 'Export reports', 'group' => 'report'],

            // SHU Management
            ['name' => 'shu.view', 'display_name' => 'View SHU', 'description' => 'View SHU information', 'group' => 'shu'],
            ['name' => 'shu.create', 'display_name' => 'Create SHU Plan', 'description' => 'Create SHU plan', 'group' => 'shu'],
            ['name' => 'shu.calculate', 'display_name' => 'Calculate SHU', 'description' => 'Calculate SHU distribution', 'group' => 'shu'],
            ['name' => 'shu.distribute', 'display_name' => 'Distribute SHU', 'description' => 'Distribute SHU to members', 'group' => 'shu'],

            // Budget Management
            ['name' => 'budget.view', 'display_name' => 'View Budget', 'description' => 'View budget information', 'group' => 'budget'],
            ['name' => 'budget.create', 'display_name' => 'Create Budget', 'description' => 'Create budget plan', 'group' => 'budget'],
            ['name' => 'budget.update', 'display_name' => 'Update Budget', 'description' => 'Update budget plan', 'group' => 'budget'],
            ['name' => 'budget.delete', 'display_name' => 'Delete Budget', 'description' => 'Delete budget plan', 'group' => 'budget'],

            // Role Management
            ['name' => 'role.view', 'display_name' => 'View Roles', 'description' => 'View role information', 'group' => 'role'],
            ['name' => 'role.create', 'display_name' => 'Create Role', 'description' => 'Create new role', 'group' => 'role'],
            ['name' => 'role.update', 'display_name' => 'Update Role', 'description' => 'Update role information', 'group' => 'role'],
            ['name' => 'role.delete', 'display_name' => 'Delete Role', 'description' => 'Delete role', 'group' => 'role'],
            ['name' => 'role.assign', 'display_name' => 'Assign Role', 'description' => 'Assign role to user', 'group' => 'role'],

            // System Management
            ['name' => 'system.view', 'display_name' => 'View System', 'description' => 'View system information', 'group' => 'system'],
            ['name' => 'system.backup', 'display_name' => 'System Backup', 'description' => 'Perform system backup', 'group' => 'system'],
            ['name' => 'system.maintenance', 'display_name' => 'System Maintenance', 'description' => 'Perform system maintenance', 'group' => 'system'],
            ['name' => 'system.audit', 'display_name' => 'View Audit Logs', 'description' => 'View system audit logs', 'group' => 'system'],
        ];

        $createdPermissions = [];

        foreach ($permissions as $permissionData) {
            $createdPermissions[] = $this->createPermission(
                name: $permissionData['name'],
                displayName: $permissionData['display_name'],
                description: $permissionData['description'],
                group: $permissionData['group'],
                isSystemPermission: true
            );
        }

        return $createdPermissions;
    }
}
