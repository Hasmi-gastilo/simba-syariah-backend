<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_number',
        'class_id',
        'academic_status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function assignments()
    {
        return $this->hasMany(PracticumAssignment::class, 'student_id');
    }

    public function grades()
    {
        return $this->hasMany(PracticumGrade::class, 'student_id');
    }
}

