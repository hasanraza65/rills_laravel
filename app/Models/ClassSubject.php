<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassSubject extends Model
{
    use HasFactory;

    protected $fillable = [
        'campus_id',
        'session_id',
        'class_id',
        'section_id',
        'teacher_id',
        'subject_name',
        'branch_id'
    ];

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function diaries()
    {
        return $this->hasMany(Diary::class);
    }

    public function topics()
    {
        return $this->hasMany(QbTopic::class, 'subject_id');
    }

    public function questions()
    {
        return $this->hasMany(QbQuestion::class, 'subject_id');
    }
}