<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancingProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_name',
        'akad_type',
        'description',
        'status',
    ];

    public function financings()
    {
        return $this->hasMany(Financing::class);
    }
}

