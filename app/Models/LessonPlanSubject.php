<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LessonPlanSubject extends Model
{
    protected $fillable = [
        'user_id',
        'subject_id',
        'branch_id',
    ];

    public function teacher()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subject()
    {
        return $this->belongsTo(QbSubject::class, 'subject_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}