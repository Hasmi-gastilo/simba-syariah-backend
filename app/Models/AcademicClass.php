<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'class_name',
        'lecturer_id',
        'academic_year',
        'semester',
    ];

    public function lecturer()
    {
        return $this->belongsTo(Lecturer::class, 'lecturer_id');
    }

    public function students()
    {
        return $this->hasMany(Student::class, 'class_id');
    }

    public function groups()
    {
        return $this->hasMany(PracticumGroup::class, 'class_id');
    }

    public function practicums()
    {
        return $this->hasMany(Practicum::class, 'class_id');
    }
}

