<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_number',
        'full_name',
        'identity_number',
        'gender',
        'birth_place',
        'birth_date',
        'address',
        'phone',
        'email',
        'occupation',
        'status',
        'created_by',
    ];

    public function accounts()
    {
        return $this->hasMany(Account::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

