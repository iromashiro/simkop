<?php
// app/Domain/Auth/DTOs/CreateRoleDTO.php
namespace App\Domain\Auth\DTOs;

class CreateRoleDTO
{
    public function __construct(
        public readonly int $cooperativeId,
        public readonly string $name,
        public readonly string $displayName,
        public readonly string $description,
        public readonly array $permissionIds = [],
        public readonly bool $isSystemRole = false,
        public readonly bool $isActive = true,
        public readonly int $createdBy
    ) {}
}
