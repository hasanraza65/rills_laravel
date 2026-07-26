<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timetable extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'timetable_group_id',
        'period_set_id',
        'title',
        'date_from',
        'date_to',
        'school_time_from',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date_from' => 'date:Y-m-d',
        'date_to' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function group()
    {
        return $this->belongsTo(TimetableGroup::class, 'timetable_group_id');
    }

    public function periodSet()
    {
        return $this->belongsTo(TimetablePeriodSet::class, 'period_set_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function slots()
    {
        return $this->hasMany(TimetableSlot::class, 'timetable_id');
    }
}
