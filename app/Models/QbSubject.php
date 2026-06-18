<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QbSubject extends Model
{
    protected $fillable = [
        'branch_id',
        'class_id',
        'name',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function topics()
    {
        return $this->hasMany(QbTopic::class, 'subject_id');
    }

    public function questions()
    {
        return $this->hasMany(QbQuestion::class, 'subject_id');
    }

    public function lessonPlanSubjects()
    {
        return $this->hasMany(LessonPlanSubject::class, 'subject_id');
    }
}