<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentFeeHead extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'head_name',
        'head_amount',
        'head_frequency',
    ];

     public function student()
    {
        return $this->belongsTo(Student::class);
    }
}
