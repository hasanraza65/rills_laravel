<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QbTopic extends Model
{
    protected $fillable = [
        'branch_id',
        'class_id',
        'subject_id',
        'name',
        'description',
        'methodology',
        'resources',
        'duration_minutes',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function subject()
    {
        return $this->belongsTo(QbSubject::class, 'subject_id');
    }

    public function attachments()
    {
        return $this->hasMany(QbTopicAttachment::class, 'topic_id');
    }

    public function objectives()
    {
        return $this->hasMany(QbTopicObjective::class, 'topic_id');
    }

    public function questions()
    {
        return $this->hasMany(QbQuestion::class, 'topic_id');
    }

    public function doneTopics()
    {
        return $this->hasMany(LessonPlanDoneTopic::class, 'topic_id');
    }
}