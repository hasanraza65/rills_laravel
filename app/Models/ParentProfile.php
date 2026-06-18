<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentProfile extends Model
{
    use HasFactory;

    protected $table = 'parent_profiles';

    protected $fillable = [

        'branch_id',
        'added_by',

        'father_name',
        'father_cnic',
        'father_education',
        'father_occupation',
        'father_contact_no',

        'mother_name',
        'mother_cnic',
        'mother_education',
        'mother_occupation',
        'mother_contact_no',

        'address',
        'guardian_type'
    ];

     public function students()
    {
        return $this->hasMany(Student::class, 'parent_id', 'id');
    }

     public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
