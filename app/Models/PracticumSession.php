<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticumSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'practicum_id',
        'group_id',
        'session_name',
        'start_time',
        'end_time',
        'virtual_initial_balance',
        'status',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'virtual_initial_balance' => 'decimal:2',
    ];

    public function practicum()
    {
        return $this->belongsTo(Practicum::class, 'practicum_id');
    }

    public function group()
    {
        return $this->belongsTo(PracticumGroup::class, 'group_id');
    }

    public function assignments()
    {
        return $this->hasMany(PracticumAssignment::class, 'session_id');
    }

    public function activities()
    {
        return $this->hasMany(PracticumActivity::class, 'session_id');
    }

    public function grades()
    {
        return $this->hasMany(PracticumGrade::class, 'session_id');
    }
}

