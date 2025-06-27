<?php
// CreateUserDTO.php
namespace App\Domain\Auth\DTOs;

class CreateUserDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly ?string $phone = null,
        public readonly ?array $roles = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            email: trim(strtolower($data['email'])),
            password: $data['password'],
            phone: $data['phone'] ?? null,
            roles: $data['roles'] ?? null,
        );
    }

    private function validate(): void
    {
        if (strlen($this->name) < 2) {
            throw new \InvalidArgumentException('Name must be at least 2 characters');
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if (strlen($this->password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
        }
    }
}

// UpdateUserDTO.php
namespace App\Domain\Auth\DTOs;

class UpdateUserDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $password = null,
        public readonly ?string $phone = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            name: !empty($data['name']) ? trim($data['name']) : null,
            email: !empty($data['email']) ? trim(strtolower($data['email'])) : null,
            password: $data['password'] ?? null,
            phone: $data['phone'] ?? null,
        );
    }
}
