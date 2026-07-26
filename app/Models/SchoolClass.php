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

    // Named addedByUser (not addedBy) so its serialized key (added_by_user) doesn't
    // collide with the existing added_by column and silently overwrite the raw id.
    public function addedByUser()
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
