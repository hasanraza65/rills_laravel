<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QbQuestion extends Model
{
    protected $fillable = [
        'branch_id',
        'class_id',
        'subject_id',
        'topic_id',
        'type',
        'question',
        'before_blank',
        'after_blank',
        'ans',
        'opt1',
        'opt2',
        'opt3',
        'opt4',
        'column_a',
        'column_b',
        'pic1',
        'marks',
    ];

    protected $casts = [
        'column_a' => 'array',
        'column_b' => 'array',
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

    public function topic()
    {
        return $this->belongsTo(QbTopic::class, 'topic_id');
    }
}