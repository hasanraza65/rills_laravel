<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimetablePeriodSetSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'period_set_id',
        'day_of_week',
        'period_number',
        'duration_minutes',
    ];

    public function periodSet()
    {
        return $this->belongsTo(TimetablePeriodSet::class, 'period_set_id');
    }
}
