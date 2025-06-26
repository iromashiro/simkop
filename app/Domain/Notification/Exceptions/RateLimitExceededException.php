<?php
// app/Domain/Notification/Exceptions/RateLimitExceededException.php
namespace App\Domain\Notification\Exceptions;

class RateLimitExceededException extends \Exception
{
    public function __construct(string $message = "Rate limit exceeded", int $code = 429, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
