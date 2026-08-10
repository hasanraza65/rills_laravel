<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamSubjectGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'section_id',
        'name',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function subjects()
    {
        return $this->belongsToMany(ClassSubject::class, 'exam_subject_group_subject', 'exam_subject_group_id', 'class_subject_id');
    }
}
