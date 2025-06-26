<?php
// app/Domain/Financial/Models/JournalLine.php
namespace App\Domain\Financial\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SECURITY HARDENED: Journal Line model with financial validation
 */
class JournalLine extends Model
{
    use HasFactory;

    // SECURITY FIX: Restricted fillable fields
    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'description',
        'debit_amount',
        'credit_amount',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    /**
     * SECURITY: Enhanced validation on boot
     */
    protected static function booted(): void
    {
        static::saving(function ($line) {
            $line->validateFinancialAmounts();
            $line->validateDoubleEntryRules();
        });
    }

    /**
     * SECURITY FIX: Validate decimal precision and negative amounts
     */
    private function validateFinancialAmounts(): void
    {
        // Validate decimal precision (max 2 decimal places)
        if (bccomp(round($this->debit_amount, 2), $this->debit_amount, 10) !== 0) {
            throw new \InvalidArgumentException('Debit amount cannot have more than 2 decimal places');
        }

        if (bccomp(round($this->credit_amount, 2), $this->credit_amount, 10) !== 0) {
            throw new \InvalidArgumentException('Credit amount cannot have more than 2 decimal places');
        }

        // Validate amounts are not negative
        if ($this->debit_amount < 0 || $this->credit_amount < 0) {
            throw new \InvalidArgumentException('Financial amounts cannot be negative');
        }

        // Validate amounts don't exceed reasonable limits (prevent overflow)
        $maxAmount = 999999999999.99; // 12 digits + 2 decimals
        if ($this->debit_amount > $maxAmount || $this->credit_amount > $maxAmount) {
            throw new \InvalidArgumentException('Amount exceeds maximum allowed value');
        }
    }

    /**
     * SECURITY: Validate double-entry rules
     */
    private function validateDoubleEntryRules(): void
    {
        // Ensure either debit or credit, not both or neither
        if (($this->debit_amount > 0 && $this->credit_amount > 0) ||
            ($this->debit_amount == 0 && $this->credit_amount == 0)
        ) {
            throw new \InvalidArgumentException(
                'Each journal line must have either debit or credit amount, not both or neither'
            );
        }
    }

    /**
     * Get the journal entry that owns this line
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account for this line
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
