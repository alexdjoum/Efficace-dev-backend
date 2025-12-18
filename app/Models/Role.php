<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name', 
        'slug', 
        'guard_name',
        'hierarchy_level', 
        'description'
    ];

    public function permissions()
    {
        // Utiliser explicitement role_permission
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function isSuperiorTo(Role $role)
    {
        return $this->hierarchy_level > $role->hierarchy_level;
    }

    public function isSuperiorOrEqualTo(Role $role)
    {
        return $this->hierarchy_level >= $role->hierarchy_level;
    }
}