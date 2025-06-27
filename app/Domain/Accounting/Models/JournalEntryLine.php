<?php

namespace App\Domain\Accounting\Models;

use App\Infrastructure\Tenancy\TenantModel;
use App\Domain\Financial\Models\Account;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Journal Entry Line Model
 *
 * Individual lines of journal entries for double-entry bookkeeping
 *
 * @package App\Domain\Accounting\Models
 * @author Mateen (Senior Software Engineer)
 */
class JournalEntryLine extends TenantModel
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'journal_entry_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'description',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    /**
     * Get journal entry
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get account
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
