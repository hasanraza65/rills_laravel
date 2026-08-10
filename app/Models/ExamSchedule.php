<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'exam_type',
        'exam_group_exam_id',
        'class_id',
        'section_id',
        'subject_id',
        'date',
        'start_time',
        'duration',
        'teacher_id',
        'total_marks',
        'min_marks',
    ];

    // No cast on `date` — kept as the raw "Y-m-d" string MySQL returns, since the
    // frontend feeds it straight into an <input type="date"> and a Carbon cast would
    // serialize it as a full ISO datetime instead.

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subject()
    {
        return $this->belongsTo(ClassSubject::class, 'subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function marks()
    {
        return $this->hasMany(ExamMark::class);
    }

    public function groupExam()
    {
        return $this->belongsTo(ExamGroupExam::class, 'exam_group_exam_id');
    }
}
