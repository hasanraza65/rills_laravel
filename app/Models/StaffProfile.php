<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    protected $table = 'staff_profiles';

    protected $fillable = [
        'user_id',
        'branch_id',
        'added_by',
        'father_husband_name',
        'gender',
        'dob',
        'date_of_joining',
        'marital_status',
        'whatsapp_no',
        'emergency_contact_no',
        'current_address',
        'permanent_address',
    ];

    protected $casts = [
        'dob'             => 'date',
        'date_of_joining' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'branch_id', 'id');
    }
}
