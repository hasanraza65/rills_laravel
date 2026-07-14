<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'color', 'is_system'];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users()
    {
        return $this->hasMany(User::class, 'user_role', 'id');
    }
}
