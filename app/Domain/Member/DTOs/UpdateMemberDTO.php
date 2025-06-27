<?php

namespace App\Domain\Member\DTOs;

/**
 * Update Member Data Transfer Object
 *
 * Handles data validation and transformation for member updates
 * Only includes updatable fields to prevent unauthorized modifications
 *
 * @package App\Domain\Member\DTOs
 * @author Mateen (Senior Software Engineer)
 */
class UpdateMemberDTO
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $address = null,
        public readonly ?string $occupation = null,
        public readonly ?float $monthly_income = null,
        public readonly ?string $emergency_contact_name = null,
        public readonly ?string $emergency_contact_phone = null,
    ) {
        $this->validate();
    }

    /**
     * Create DTO from array data
     *
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: !empty($data['name']) ? trim($data['name']) : null,
            email: !empty($data['email']) ? trim(strtolower($data['email'])) : null,
            phone: !empty($data['phone']) ? self::normalizePhone($data['phone']) : null,
            address: !empty($data['address']) ? trim($data['address']) : null,
            occupation: !empty($data['occupation']) ? trim($data['occupation']) : null,
            monthly_income: !empty($data['monthly_income']) ? (float)$data['monthly_income'] : null,
            emergency_contact_name: !empty($data['emergency_contact_name']) ? trim($data['emergency_contact_name']) : null,
            emergency_contact_phone: !empty($data['emergency_contact_phone']) ? self::normalizePhone($data['emergency_contact_phone']) : null,
        );
    }

    /**
     * Create DTO from HTTP request
     *
     * @param \Illuminate\Http\Request $request
     * @return self
     */
    public static function fromRequest($request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Convert to array for database update
     *
     * @return array
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'occupation' => $this->occupation,
            'monthly_income' => $this->monthly_income,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
        ], fn($value) => $value !== null);
    }

    /**
     * Validate DTO data
     *
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->name !== null && (empty($this->name) || strlen($this->name) < 2)) {
            throw new \InvalidArgumentException('Name must be at least 2 characters');
        }

        if ($this->email !== null && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if ($this->monthly_income !== null && $this->monthly_income < 0) {
            throw new \InvalidArgumentException('Monthly income cannot be negative');
        }
    }

    /**
     * Normalize phone number format
     *
     * @param string $phone
     * @return string
     */
    private static function normalizePhone(string $phone): string
    {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Convert to Indonesian format
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Check if any field has value
     *
     * @return bool
     */
    public function hasUpdates(): bool
    {
        return !empty(array_filter($this->toArray()));
    }
}
