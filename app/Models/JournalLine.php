<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalLine extends Model
{
    use HasFactory;

    protected $fillable = [
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
     * Get the journal entry that owns the line.
     */
    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Get the account for the line.
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }
}
