<?php

namespace App\Domain\Member\Exceptions;

use Exception;

/**
 * Member Not Found Exception
 *
 * Thrown when a requested member cannot be found in the system
 * Provides specific error handling for member-related operations
 *
 * @package App\Domain\Member\Exceptions
 * @author Mateen (Senior Software Engineer)
 */
class MemberNotFoundException extends Exception
{
    protected $message = 'Member not found';
    protected $code = 404;

    public function __construct(string $message = null, int $memberId = null, \Throwable $previous = null)
    {
        if ($message) {
            $this->message = $message;
        } elseif ($memberId) {
            $this->message = "Member with ID {$memberId} not found";
        }

        parent::__construct($this->message, $this->code, $previous);
    }

    /**
     * Create exception for specific member ID
     */
    public static function forId(int $memberId): self
    {
        return new self("Member with ID {$memberId} not found", $memberId);
    }

    /**
     * Create exception for member number
     */
    public static function forMemberNumber(string $memberNumber): self
    {
        return new self("Member with number {$memberNumber} not found");
    }

    /**
     * Create exception for email
     */
    public static function forEmail(string $email): self
    {
        return new self("Member with email {$email} not found");
    }

    /**
     * Get error response for API
     */
    public function getApiResponse(): array
    {
        return [
            'success' => false,
            'error' => 'MEMBER_NOT_FOUND',
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }
}
