<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetablePeriodSet extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'timetable_group_id',
        'title',
        'added_by',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function group()
    {
        return $this->belongsTo(TimetableGroup::class, 'timetable_group_id');
    }

    public function slots()
    {
        return $this->hasMany(TimetablePeriodSetSlot::class, 'period_set_id')
            ->orderBy('day_of_week')
            ->orderBy('period_number');
    }
}
