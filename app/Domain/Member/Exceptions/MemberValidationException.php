<?php

namespace App\Domain\Member\Exceptions;

use Exception;

/**
 * Member Validation Exception
 *
 * Thrown when member data fails business rule validation
 * Provides detailed validation error information
 *
 * @package App\Domain\Member\Exceptions
 * @author Mateen (Senior Software Engineer)
 */
class MemberValidationException extends Exception
{
    protected $message = 'Member validation failed';
    protected $code = 422;
    protected array $errors = [];

    public function __construct(string $message = null, array $errors = [], \Throwable $previous = null)
    {
        $this->errors = $errors;

        if ($message) {
            $this->message = $message;
        } elseif (!empty($errors)) {
            $this->message = 'Member validation failed: ' . implode(', ', array_keys($errors));
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    /**
     * Create exception for age validation
     */
    public static function invalidAge(int $age): self
    {
        return new self(
            "Invalid age: {$age}. Member must be at least 17 years old",
            ['age' => ["Member must be at least 17 years old, got {$age}"]]
        );
    }

    /**
     * Create exception for duplicate member
     */
    public static function duplicateMember(string $field, string $value): self
    {
        return new self(
            "Duplicate member: {$field} '{$value}' already exists",
            [$field => ["The {$field} '{$value}' is already registered"]]
        );
    }

    /**
     * Create exception for invalid status transition
     */
    public static function invalidStatusTransition(string $from, string $to): self
    {
        return new self(
            "Invalid status transition from '{$from}' to '{$to}'",
            ['status' => ["Cannot change status from '{$from}' to '{$to}'"]]
        );
    }

    /**
     * Create exception for outstanding balance
     */
    public static function outstandingBalance(float $balance): self
    {
        return new self(
            "Cannot terminate member with outstanding balance: " . number_format($balance, 2),
            ['balance' => ["Outstanding balance of " . number_format($balance, 2) . " must be settled first"]]
        );
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get error response for API
     */
    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => 'MEMBER_VALIDATION_FAILED',
            'message' => $this->getMessage(),
            'errors' => $this->getErrors(),
            'code' => $this->getCode(),
        ];
    }
}
