<?php
// AccountNotFoundException.php
namespace App\Domain\Accounting\Exceptions;

use Exception;

class AccountNotFoundException extends Exception
{
    protected $message = 'Account not found';
    protected $code = 404;

    public static function forId(int $id): self
    {
        return new self("Account with ID {$id} not found");
    }

    public static function forCode(string $code): self
    {
        return new self("Account with code {$code} not found");
    }
}

// AccountValidationException.php
namespace App\Domain\Accounting\Exceptions;

use Exception;

class AccountValidationException extends Exception
{
    protected $message = 'Account validation failed';
    protected $code = 422;

    public static function duplicateCode(string $code): self
    {
        return new self("Account code '{$code}' already exists");
    }

    public static function invalidParent(int $parentId): self
    {
        return new self("Invalid parent account ID: {$parentId}");
    }

    public static function invalidType(string $type): self
    {
        return new self("Invalid account type: {$type}");
    }
}

// FiscalPeriodValidationException.php
namespace App\Domain\Accounting\Exceptions;

use Exception;
use Carbon\Carbon;

class FiscalPeriodValidationException extends Exception
{
    protected $message = 'Fiscal period validation failed';
    protected $code = 422;

    public static function overlappingPeriod(Carbon $startDate, Carbon $endDate): self
    {
        return new self("Fiscal period overlaps with existing period: {$startDate->format('Y-m-d')} to {$endDate->format('Y-m-d')}");
    }

    public static function invalidDateRange(Carbon $startDate, Carbon $endDate): self
    {
        return new self("Invalid date range: start date must be before end date");
    }
}

// JournalEntryValidationException.php
namespace App\Domain\Accounting\Exceptions;

use Exception;

class JournalEntryValidationException extends Exception
{
    protected $message = 'Journal entry validation failed';
    protected $code = 422;

    public static function unbalancedEntry(float $debit, float $credit): self
    {
        return new self("Journal entry is not balanced: Debit {$debit} != Credit {$credit}");
    }

    public static function invalidFiscalPeriod(int $periodId): self
    {
        return new self("Invalid or inactive fiscal period: {$periodId}");
    }

    public static function invalidAccount(int $accountId): self
    {
        return new self("Invalid account ID: {$accountId}");
    }

    public static function insufficientLines(): self
    {
        return new self("Journal entry must have at least 2 lines");
    }
}
