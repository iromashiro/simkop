<?php

namespace App\Domain\Cooperative\DTOs;

use Carbon\Carbon;

class CreateCooperativeDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $code,
        public readonly string $registration_number,
        public readonly string $address,
        public readonly string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly Carbon $established_date,
        public readonly string $legal_entity_type,
        public readonly string $business_type,
        public readonly string $chairman_name,
        public readonly string $secretary_name,
        public readonly string $treasurer_name,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        return new self(
            name: trim($data['name']),
            code: strtoupper(trim($data['code'])),
            registration_number: trim($data['registration_number']),
            address: trim($data['address']),
            phone: self::normalizePhone($data['phone']),
            email: !empty($data['email']) ? trim(strtolower($data['email'])) : null,
            website: !empty($data['website']) ? trim($data['website']) : null,
            established_date: Carbon::parse($data['established_date']),
            legal_entity_type: $data['legal_entity_type'],
            business_type: $data['business_type'],
            chairman_name: trim($data['chairman_name']),
            secretary_name: trim($data['secretary_name']),
            treasurer_name: trim($data['treasurer_name']),
        );
    }

    private function validate(): void
    {
        if (strlen($this->name) < 3) {
            throw new \InvalidArgumentException('Cooperative name must be at least 3 characters');
        }

        if (strlen($this->code) < 2 || strlen($this->code) > 10) {
            throw new \InvalidArgumentException('Cooperative code must be 2-10 characters');
        }

        if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if ($this->established_date->isAfter(now())) {
            throw new \InvalidArgumentException('Established date cannot be in the future');
        }
    }

    private static function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($phone, '0')) {
            return '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            return '62' . $phone;
        }

        return $phone;
    }
}
