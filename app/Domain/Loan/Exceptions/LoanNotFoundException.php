<?php
// LoanNotFoundException.php
namespace App\Domain\Loan\Exceptions;

use Exception;

class LoanNotFoundException extends Exception
{
    protected $message = 'Loan account not found';
    protected $code = 404;

    public static function forId(int $id): self
    {
        return new self("Loan account with ID {$id} not found");
    }

    public static function forLoanNumber(string $loanNumber): self
    {
        return new self("Loan account with number {$loanNumber} not found");
    }
}

// LoanValidationException.php
namespace App\Domain\Loan\Exceptions;

use Exception;

class LoanValidationException extends Exception
{
    protected $message = 'Loan validation failed';
    protected $code = 422;

    public static function invalidAmount(float $amount): self
    {
        return new self("Invalid loan amount: {$amount}");
    }

    public static function invalidInterestRate(float $rate): self
    {
        return new self("Invalid interest rate: {$rate}%");
    }

    public static function invalidTerm(int $months): self
    {
        return new self("Invalid loan term: {$months} months");
    }

    public static function memberNotEligible(int $memberId): self
    {
        return new self("Member {$memberId} is not eligible for loans");
    }

    public static function insufficientPayment(float $payment, float $required): self
    {
        return new self("Insufficient payment amount: {$payment}, required: {$required}");
    }

    public static function invalidDisbursementDate(\Carbon\Carbon $date): self
    {
        return new self("Invalid disbursement date: {$date->format('Y-m-d')}");
    }

    public static function loanNotActive(int $loanId): self
    {
        return new self("Loan account {$loanId} is not active");
    }

    public static function excessivePayment(float $payment, float $balance): self
    {
        return new self("Payment amount {$payment} exceeds outstanding balance {$balance}");
    }

    public static function invalidPaymentAmount(float $amount): self
    {
        return new self("Invalid payment amount: {$amount}");
    }
}
