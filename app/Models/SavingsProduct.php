<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SavingsProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_code',
        'product_name',
        'akad_type',
        'minimum_balance',
        'initial_deposit',
        'nisbah_customer',
        'nisbah_bank',
        'tenor_options',
        'status',
    ];

    protected $casts = [
        'minimum_balance' => 'decimal:2',
        'initial_deposit' => 'decimal:2',
        'nisbah_customer' => 'decimal:2',
        'nisbah_bank' => 'decimal:2',
        'tenor_options' => 'array',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }
}

