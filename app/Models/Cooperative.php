<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cooperative extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'kementerian_id',
        'registration_number',
        'address',
        'phone',
        'email',
        'chairman_name',
        'manager_name',
        'establishment_date',
        'business_type',
        'status',
        'member_count',
        'asset_value',
    ];

    protected $casts = [
        'establishment_date' => 'date',
        'asset_value' => 'decimal:2',
        'member_count' => 'integer',
    ];

    /**
     * Get the users for the cooperative.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the members for the cooperative.
     */
    public function members()
    {
        return $this->hasMany(Member::class);
    }

    /**
     * Get the accounts for the cooperative.
     */
    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
}
