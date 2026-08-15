<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'added_by',
        'branch_admin_id',
        'branch_name',
        'branch_code',
        'campus_start_date',
        'campus_phone',
        'campus_email',
        'branch_city',
        'branch_address',
        'branch_phone',
    ];
}
