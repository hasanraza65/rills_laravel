<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamMark extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_schedule_id',
        'student_id',
        'obtained_marks',
        'is_absent',
        'note',
    ];

    protected $casts = [
        'is_absent' => 'boolean',
    ];

    public function schedule()
    {
        return $this->belongsTo(ExamSchedule::class, 'exam_schedule_id');
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
