<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetableGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'added_by',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'timetable_group_classes', 'timetable_group_id', 'school_class_id');
    }

    public function periodSets()
    {
        return $this->hasMany(TimetablePeriodSet::class, 'timetable_group_id');
    }

    public function timetables()
    {
        return $this->hasMany(Timetable::class, 'timetable_group_id');
    }
}
