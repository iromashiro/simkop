<?php
// app/Domain/Financial/Enums/AccountType.php
namespace App\Domain\Financial\Enums;

/**
 * Account Type Constants
 * Standardized account types for consistent usage across the system
 */
class AccountType
{
    public const ASSET = 'ASSET';
    public const LIABILITY = 'LIABILITY';
    public const EQUITY = 'EQUITY';
    public const REVENUE = 'REVENUE';
    public const EXPENSE = 'EXPENSE';

    public const SUBTYPES = [
        self::ASSET => [
            'current',
            'non_current',
            'fixed',
            'intangible'
        ],
        self::LIABILITY => [
            'current',
            'non_current',
            'debt'
        ],
        self::EQUITY => [
            'capital',
            'retained_earnings',
            'other_comprehensive_income'
        ],
        self::REVENUE => [
            'operating',
            'non_operating',
            'interest',
            'other'
        ],
        self::EXPENSE => [
            'cogs',
            'operating',
            'non_operating',
            'interest',
            'depreciation',
            'amortization'
        ]
    ];

    public const NORMAL_BALANCES = [
        self::ASSET => 'debit',
        self::LIABILITY => 'credit',
        self::EQUITY => 'credit',
        self::REVENUE => 'credit',
        self::EXPENSE => 'debit'
    ];

    /**
     * Get all account types
     */
    public static function all(): array
    {
        return [
            self::ASSET,
            self::LIABILITY,
            self::EQUITY,
            self::REVENUE,
            self::EXPENSE
        ];
    }

    /**
     * Get subtypes for account type
     */
    public static function getSubtypes(string $accountType): array
    {
        return self::SUBTYPES[$accountType] ?? [];
    }

    /**
     * Get normal balance for account type
     */
    public static function getNormalBalance(string $accountType): string
    {
        return self::NORMAL_BALANCES[$accountType] ?? 'debit';
    }

    /**
     * Check if account type is valid
     */
    public static function isValid(string $accountType): bool
    {
        return in_array($accountType, self::all());
    }

    /**
     * Check if subtype is valid for account type
     */
    public static function isValidSubtype(string $accountType, string $subtype): bool
    {
        return in_array($subtype, self::getSubtypes($accountType));
    }
}
