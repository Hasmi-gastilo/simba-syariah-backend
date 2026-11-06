<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Financing extends Model
{
    use HasFactory;

    protected $fillable = [
        'financing_number',
        'customer_id',
        'financing_product_id',
        'principal_amount',
        'margin_amount',
        'selling_price',
        'profit_sharing_ratio',
        'tenor',
        'installment_amount',
        'purpose',
        'status',
        'submitted_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'margin_amount' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(FinancingProduct::class, 'financing_product_id');
    }

    public function installments()
    {
        return $this->hasMany(FinancingInstallment::class)->orderBy('installment_number');
    }

    public function documents()
    {
        return $this->hasMany(FinancingDocument::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}

