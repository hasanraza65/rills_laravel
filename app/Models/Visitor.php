<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'added_by',
        'branch_id',
        'name',
        'phone',
        'cnic',
        'reason',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class);
    }

    
}
