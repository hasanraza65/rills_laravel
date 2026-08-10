<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'exam_type',
        'description',
        'added_by',
    ];

    public function exams()
    {
        return $this->hasMany(ExamGroupExam::class);
    }
}
