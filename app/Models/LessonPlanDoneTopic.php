<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonPlanDoneTopic extends Model
{
    protected $fillable = [
        'topic_id',
        'teacher_id',
        'subject_id',
        'branch_id',
        'completed_date',
    ];

    protected $casts = [
        'completed_date' => 'date',
    ];

    public function topic()
    {
        return $this->belongsTo(QbTopic::class, 'topic_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function subject()
    {
        return $this->belongsTo(QbSubject::class, 'subject_id');
    }
}