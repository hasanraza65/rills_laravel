<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'branch_id',
        'added_by'
    ];

    public function sections()
    {
        return $this->hasMany(Section::class, 'school_class_id');
    }
}
