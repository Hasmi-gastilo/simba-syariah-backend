<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinancingDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'financing_id',
        'document_name',
        'file_path',
        'uploaded_by',
    ];

    public function financing()
    {
        return $this->belongsTo(Financing::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

