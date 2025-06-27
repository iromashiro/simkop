<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'cooperative_id',
        'member_number',
        'name',
        'email',
        'phone',
        'id_number',
        'id_type',
        'birth_date',
        'birth_place',
        'gender',
        'address',
        'occupation',
        'monthly_income',
        'join_date',
        'status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'join_date' => 'date',
        'monthly_income' => 'decimal:2',
    ];

    /**
     * Get the cooperative that owns the member.
     */
    public function cooperative()
    {
        return $this->belongsTo(Cooperative::class);
    }

    /**
     * Get the savings for the member.
     */
    public function savings()
    {
        return $this->hasMany(Savings::class);
    }

    /**
     * Get the loans for the member.
     */
    public function loans()
    {
        return $this->hasMany(Loan::class);
    }
}
