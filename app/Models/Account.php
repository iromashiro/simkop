<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'code',
        'name',
        'type',
        'parent_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the cooperative that owns the account.
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get the journal lines for the account.
     */
    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
