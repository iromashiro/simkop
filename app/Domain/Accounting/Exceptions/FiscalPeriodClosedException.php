<?php

namespace App\Domain\Accounting\Exceptions;

use Exception;

class FiscalPeriodClosedException extends Exception
{
    protected $message = 'The fiscal period is already closed and cannot be modified.';

    public function __construct(string $message = null, int $code = 0, Exception $previous = null)
    {
        parent::__construct($message ?? $this->message, $code, $previous);
    }

    public static function forPeriod(string $period): self
    {
        return new self("Fiscal period '{$period}' is already closed and cannot be modified.");
    }

    public static function withCustomMessage(string $message): self
    {
        return new self($message);
    }
}
