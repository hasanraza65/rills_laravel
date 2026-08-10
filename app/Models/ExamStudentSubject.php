<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamStudentSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'class_subject_id',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function subject()
    {
        return $this->belongsTo(ClassSubject::class, 'class_subject_id');
    }
}
