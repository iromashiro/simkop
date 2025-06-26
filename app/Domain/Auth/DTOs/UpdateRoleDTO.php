<?php
// app/Domain/Auth/DTOs/UpdateRoleDTO.php
namespace App\Domain\Auth\DTOs;

class UpdateRoleDTO
{
    public function __construct(
        public readonly ?string $displayName = null,
        public readonly ?string $description = null,
        public readonly ?array $permissionIds = null,
        public readonly ?bool $isActive = null
    ) {}
}
