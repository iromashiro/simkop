<?php
// SavingsNotFoundException.php
namespace App\Domain\Savings\Exceptions;

use Exception;

class SavingsNotFoundException extends Exception
{
    protected $message = 'Savings account not found';
    protected $code = 404;

    public static function forId(int $id): self
    {
        return new self("Savings account with ID {$id} not found");
    }

    public static function forAccountNumber(string $accountNumber): self
    {
        return new self("Savings account with number {$accountNumber} not found");
    }
}

// SavingsValidationException.php
namespace App\Domain\Savings\Exceptions;

use Exception;

class SavingsValidationException extends Exception
{
    protected $message = 'Savings validation failed';
    protected $code = 422;

    public static function insufficientBalance(float $balance, float $withdrawal): self
    {
        return new self("Insufficient balance: {$balance}, withdrawal: {$withdrawal}");
    }

    public static function belowMinimumBalance(float $balance, float $minimum): self
    {
        return new self("Balance {$balance} is below minimum required: {$minimum}");
    }

    public static function invalidTransactionType(string $type): self
    {
        return new self("Invalid transaction type: {$type}");
    }

    public static function accountInactive(int $accountId): self
    {
        return new self("Savings account {$accountId} is inactive");
    }

    public static function memberNotEligible(int $memberId, string $reason = ''): self
    {
        $message = "Member {$memberId} is not eligible for savings account";
        if ($reason) {
            $message .= ": {$reason}";
        }
        return new self($message);
    }

    public static function invalidInterestRate(float $rate): self
    {
        return new self("Invalid interest rate: {$rate}%");
    }

    public static function invalidMinimumBalance(float $balance): self
    {
        return new self("Invalid minimum balance: {$balance}");
    }

    public static function invalidInitialDeposit(float $deposit): self
    {
        return new self("Invalid initial deposit: {$deposit}");
    }

    public static function initialDepositBelowMinimum(float $deposit, float $minimum): self
    {
        return new self("Initial deposit {$deposit} is below minimum balance {$minimum}");
    }

    public static function invalidTransactionAmount(float $amount): self
    {
        return new self("Invalid transaction amount: {$amount}");
    }

    public static function accountHasBalance(int $accountId, float $balance): self
    {
        return new self("Cannot close account {$accountId} with balance {$balance}");
    }
}
