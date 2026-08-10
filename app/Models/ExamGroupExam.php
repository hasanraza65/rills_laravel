<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamGroupExam extends Model
{
    use HasFactory;

    protected $fillable = [
        'exam_group_id',
        'name',
        'publish_exam',
        'publish_schedule',
        'publish_result',
        'description',
    ];

    protected $casts = [
        'publish_exam' => 'boolean',
        'publish_schedule' => 'boolean',
        'publish_result' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(ExamGroup::class, 'exam_group_id');
    }

    public function schedules()
    {
        return $this->hasMany(ExamSchedule::class);
    }
}
