<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Syllabus extends Model
{
    use HasFactory;

    protected $table = 'syllabuses'; // 👈 THIS FIXES IT

    protected $fillable = [
        'subject_id',
        'month',
        'content',
        'status',
        'campus_id',
        'session_id',
        'branch_id'
    ];

    protected $casts = [
        'month' => 'date',
    ];

    public function subject()
    {
        return $this->belongsTo(ClassSubject::class, 'subject_id');
    }
}