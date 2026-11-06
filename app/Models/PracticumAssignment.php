<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticumAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'student_id',
        'role_id',
        'assigned_by',
    ];

    public function session()
    {
        return $this->belongsTo(PracticumSession::class, 'session_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function assigner()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

