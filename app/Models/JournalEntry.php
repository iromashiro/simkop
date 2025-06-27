<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'entry_number',
        'transaction_date',
        'description',
        'reference_type',
        'reference_id',
        'total_debit',
        'total_credit',
        'status',
        'created_by',
        'posted_by',
        'posted_at',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'total_debit' => 'decimal:2',
        'total_credit' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    /**
     * Get the cooperative that owns the journal entry.
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get the lines for the journal entry.
     */
    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * Get the user who created the entry.
     */
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who posted the entry.
     */
    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
