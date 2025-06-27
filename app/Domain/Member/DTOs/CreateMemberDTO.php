<?php

namespace App\Domain\Member\DTOs;

use Carbon\Carbon;

/**
 * Create Member Data Transfer Object
 *
 * Handles data validation and transformation for member creation
 * Ensures data integrity and type safety for member registration
 *
 * @package App\Domain\Member\DTOs
 * @author Mateen (Senior Software Engineer)
 */
class CreateMemberDTO
{
    public function __construct(
        public readonly int $cooperative_id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly string $phone,
        public readonly string $address,
        public readonly string $id_number,
        public readonly string $id_type, // KTP, SIM, PASSPORT
        public readonly Carbon $date_of_birth,
        public readonly string $gender, // male, female
        public readonly ?string $occupation,
        public readonly ?float $monthly_income,
        public readonly string $emergency_contact_name,
        public readonly string $emergency_contact_phone,
        public readonly ?Carbon $join_date = null,
        public readonly bool $create_user_account = false,
        public readonly ?string $password = null,
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
            cooperative_id: $data['cooperative_id'],
            name: trim($data['name']),
            email: !empty($data['email']) ? trim(strtolower($data['email'])) : null,
            phone: self::normalizePhone($data['phone']),
            address: trim($data['address']),
            id_number: trim($data['id_number']),
            id_type: strtoupper($data['id_type']),
            date_of_birth: Carbon::parse($data['date_of_birth']),
            gender: strtolower($data['gender']),
            occupation: !empty($data['occupation']) ? trim($data['occupation']) : null,
            monthly_income: !empty($data['monthly_income']) ? (float)$data['monthly_income'] : null,
            emergency_contact_name: trim($data['emergency_contact_name']),
            emergency_contact_phone: self::normalizePhone($data['emergency_contact_phone']),
            join_date: !empty($data['join_date']) ? Carbon::parse($data['join_date']) : null,
            create_user_account: (bool)($data['create_user_account'] ?? false),
            password: $data['password'] ?? null,
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
     * Convert to array for database storage
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'cooperative_id' => $this->cooperative_id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'id_number' => $this->id_number,
            'id_type' => $this->id_type,
            'date_of_birth' => $this->date_of_birth->format('Y-m-d'),
            'gender' => $this->gender,
            'occupation' => $this->occupation,
            'monthly_income' => $this->monthly_income,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'create_user_account' => $this->create_user_account,
        ];
    }

    /**
     * Validate DTO data
     *
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty($this->name) || strlen($this->name) < 2) {
            throw new \InvalidArgumentException('Name must be at least 2 characters');
        }

        if ($this->email && !filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email format');
        }

        if (!in_array($this->id_type, ['KTP', 'SIM', 'PASSPORT'])) {
            throw new \InvalidArgumentException('Invalid ID type');
        }

        if (!in_array($this->gender, ['male', 'female'])) {
            throw new \InvalidArgumentException('Invalid gender');
        }

        if ($this->date_of_birth->isAfter(now()->subYears(17))) {
            throw new \InvalidArgumentException('Member must be at least 17 years old');
        }

        if ($this->monthly_income && $this->monthly_income < 0) {
            throw new \InvalidArgumentException('Monthly income cannot be negative');
        }

        if ($this->create_user_account && (!$this->email || !$this->password)) {
            throw new \InvalidArgumentException('Email and password required for user account creation');
        }

        if ($this->password && strlen($this->password) < 8) {
            throw new \InvalidArgumentException('Password must be at least 8 characters');
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
     * Get member age
     *
     * @return int
     */
    public function getAge(): int
    {
        return $this->date_of_birth->age;
    }

    /**
     * Check if member is eligible for loans
     *
     * @return bool
     */
    public function isEligibleForLoans(): bool
    {
        return $this->getAge() >= 21 && $this->monthly_income >= 1000000; // Minimum 1M IDR
    }
}
