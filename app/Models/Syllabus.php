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
        'page',
        'link',
        'content',
        'status',
        'branch_id'
    ];

    /**
     * Pin the date format. An unformatted `date` cast serializes as an ISO
     * timestamp, which silently blanks every <input type="date"> on the frontend.
     */
    protected $casts = [
        'month' => 'date:Y-m-d',
    ];

    public function subject()
    {
        return $this->belongsTo(ClassSubject::class, 'subject_id');
    }
}