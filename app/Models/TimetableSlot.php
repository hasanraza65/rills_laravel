<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetableSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'timetable_id',
        'section_id',
        'day_of_week',
        'period_number',
        'start_time',
        'end_time',
        'class_subject_id',
        'teacher_id',
        'timetable_activity_id',
    ];

    public function timetable()
    {
        return $this->belongsTo(Timetable::class, 'timetable_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function classSubject()
    {
        return $this->belongsTo(ClassSubject::class, 'class_subject_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function activity()
    {
        return $this->belongsTo(TimetableActivity::class, 'timetable_activity_id');
    }
}
