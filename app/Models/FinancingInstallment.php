<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancingInstallment extends Model
{
    use HasFactory;

    protected $fillable = [
        'financing_id',
        'installment_number',
        'due_date',
        'amount_due',
        'amount_paid',
        'paid_at',
        'status',
    ];

    protected $casts = [
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function financing()
    {
        return $this->belongsTo(Financing::class);
    }
}

