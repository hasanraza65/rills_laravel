<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'added_by',
        'branch_id',
        'admission_no',
        'admission_date',
        'photo',
        'name',
        'dob',
        'gender',
        'nationality',
        'address',
        'home_contact',
        'currently_studying',
        'class_id',
        'section_id',
        'previous_schools',
        'health_issues',
        'health_details',
        'parent_id',
        'source',
        'attachments'
    ];

    protected $casts = [
        'previous_schools' => 'array',
        'health_issues' => 'array',
        'attachments' => 'array',
        'admission_date' => 'date',
        'dob' => 'date'
    ];

    public function class()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function parent()
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }

    public function siblings()
    {
        return $this->hasMany(Student::class, 'parent_id', 'parent_id')
            ->where('id', '!=', $this->id);
    }

    public function feeHeads()
    {
        return $this->hasMany(StudentFeeHead::class);
    }
}
