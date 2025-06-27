<?php

namespace App\Domain\Member\Exceptions;

use Exception;

/**
 * Cooperative Access Denied Exception
 *
 * Thrown when user attempts to access cooperative data without proper authorization
 * Ensures multi-tenant security and data isolation
 *
 * @package App\Domain\Member\Exceptions
 * @author Mateen (Senior Software Engineer)
 */
class CooperativeAccessDeniedException extends Exception
{
    protected $message = 'Access denied to cooperative';
    protected $code = 403;

    public function __construct(string $message = null, int $cooperativeId = null, \Throwable $previous = null)
    {
        if ($message) {
            $this->message = $message;
        } elseif ($cooperativeId) {
            $this->message = "Access denied to cooperative ID {$cooperativeId}";
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    /**
     * Create exception for specific cooperative ID
     */
    public static function forCooperative(int $cooperativeId): self
    {
        return new self("Access denied to cooperative ID {$cooperativeId}", $cooperativeId);
    }

    /**
     * Create exception for user without cooperative access
     */
    public static function forUser(int $userId, int $cooperativeId): self
    {
        return new self("User {$userId} does not have access to cooperative {$cooperativeId}");
    }

    /**
     * Get error response for API
     */
    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => 'COOPERATIVE_ACCESS_DENIED',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
