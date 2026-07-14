<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Diary extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_subject_id',
        'topic',
        'activity',
        'page_number',
        'resources',
        'link',
        'home_work',
        'date',
        'status',
        'branch_id',
    ];

    /**
     * Pin the date format. An unformatted `date` cast serializes as an ISO timestamp,
     * which silently blanks every <input type="date"> on the frontend.
     */
    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function classSubject()
    {
        return $this->belongsTo(ClassSubject::class);
    }
}