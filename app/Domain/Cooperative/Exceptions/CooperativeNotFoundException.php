<?php
// CooperativeNotFoundException.php
namespace App\Domain\Cooperative\Exceptions;

use Exception;

class CooperativeNotFoundException extends Exception
{
    protected $message = 'Cooperative not found';
    protected $code = 404;

    public static function forId(int $id): self
    {
        return new self("Cooperative with ID {$id} not found");
    }

    public static function forCode(string $code): self
    {
        return new self("Cooperative with code {$code} not found");
    }

    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => 'COOPERATIVE_NOT_FOUND',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}

// CooperativeValidationException.php
namespace App\Domain\Cooperative\Exceptions;

use Exception;

class CooperativeValidationException extends Exception
{
    protected $message = 'Cooperative validation failed';
    protected $code = 422;

    public static function duplicateField(string $field, string $value): self
    {
        return new self("Duplicate {$field}: '{$value}' already exists");
    }

    public static function invalidEstablishedDate(): self
    {
        return new self("Established date cannot be in the future");
    }

    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => 'COOPERATIVE_VALIDATION_FAILED',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
