<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PracticumGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'group_name',
        'description',
    ];

    public function academicClass()
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function members()
    {
        return $this->belongsToMany(Student::class, 'practicum_group_members', 'group_id', 'student_id')
            ->withTimestamps();
    }

    public function sessions()
    {
        return $this->hasMany(PracticumSession::class, 'group_id');
    }
}

