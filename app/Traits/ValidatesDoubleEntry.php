<?php
// app/Traits/ValidatesDoubleEntry.php
namespace App\Traits;

use App\Domain\Financial\Exceptions\UnbalancedEntryException;

/**
 * Trait for validating double-entry accounting rules
 *
 * Ensures all journal entries maintain the fundamental
 * accounting equation and double-entry principles
 */
trait ValidatesDoubleEntry
{
    /**
     * Boot the trait
     */
    public static function bootValidatesDoubleEntry(): void
    {
        // Validate balance before saving
        static::saving(function ($model) {
            if ($model->isDirty(['total_debit', 'total_credit'])) {
                $model->validateDoubleEntryBalance();
            }
        });
    }

    /**
     * Validate that debits equal credits
     */
    public function validateDoubleEntryBalance(): void
    {
        $difference = abs($this->total_debit - $this->total_credit);

        if ($difference > 0.01) { // Allow for minor rounding differences
            throw new UnbalancedEntryException(
                "Journal entry must be balanced. " .
                    "Debit: {$this->total_debit}, Credit: {$this->total_credit}, " .
                    "Difference: {$difference}"
            );
        }
    }

    /**
     * Validate journal lines follow double-entry rules
     */
    public function validateJournalLines(): void
    {
        $lines = $this->lines ?? collect();

        if ($lines->count() < 2) {
            throw new UnbalancedEntryException(
                'Journal entry must have at least 2 lines (minimum one debit and one credit)'
            );
        }

        $totalDebit = $lines->sum('debit_amount');
        $totalCredit = $lines->sum('credit_amount');

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new UnbalancedEntryException(
                "Journal lines must be balanced. " .
                    "Total Debit: {$totalDebit}, Total Credit: {$totalCredit}"
            );
        }

        // Validate each line has either debit or credit, not both
        foreach ($lines as $line) {
            if (($line->debit_amount > 0 && $line->credit_amount > 0) ||
                ($line->debit_amount == 0 && $line->credit_amount == 0)
            ) {
                throw new UnbalancedEntryException(
                    'Each journal line must have either debit or credit amount, not both or neither'
                );
            }
        }
    }
}
