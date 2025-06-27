<?php

namespace App\Domain\Cooperative\DTOs;

class UpdateCooperativeDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $address = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $website = null,
        public readonly ?string $chairman_name = null,
        public readonly ?string $secretary_name = null,
        public readonly ?string $treasurer_name = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: !empty($data['name']) ? trim($data['name']) : null,
            address: !empty($data['address']) ? trim($data['address']) : null,
            phone: !empty($data['phone']) ? self::normalizePhone($data['phone']) : null,
            email: !empty($data['email']) ? trim(strtolower($data['email'])) : null,
            website: !empty($data['website']) ? trim($data['website']) : null,
            chairman_name: !empty($data['chairman_name']) ? trim($data['chairman_name']) : null,
            secretary_name: !empty($data['secretary_name']) ? trim($data['secretary_name']) : null,
            treasurer_name: !empty($data['treasurer_name']) ? trim($data['treasurer_name']) : null,
        );
    }

    private function validate(): void
    {
        if ($this->name !== null && strlen($this->name) < 3) {
            throw new \InvalidArgumentException('Cooperative name must be at least 3 characters');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }
    }

    private static function normalizePhone(?string $phone): ?string
    {
        if (!$phone) return null;

        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            return '62' . $phone;
        }

        return $phone;
    }
}
