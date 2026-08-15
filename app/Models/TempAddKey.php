<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TempAddKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'added_by',
        'branch_id',
        'key',
        'visitor_name',
        'address',
        'purpose',
        'remarks',
        'students',
    ];

    protected $casts = [
        'students' => 'array',
    ];
}
