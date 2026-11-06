<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticumActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'user_id',
        'activity_type',
        'description',
        'reference_id',
    ];

    public function session()
    {
        return $this->belongsTo(PracticumSession::class, 'session_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

