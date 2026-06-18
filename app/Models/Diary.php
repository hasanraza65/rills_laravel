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
        'description',
        'page_number',
        'resources',
        'link',
        'home_work',
        'date',
        'status',
        'branch_id',
    ];

    public function classSubject()
    {
        return $this->belongsTo(ClassSubject::class);
    }
}