<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeHead extends Model
{
    use HasFactory;

    protected $fillable = [
        'added_by',
        'branch_id',
        'section_id',
        'head_name',
        'head_amount',
        'head_frequency',
    ];

    public function section()
    {
        return $this->belongsTo(Section::class, 'section_id');
    }
}
