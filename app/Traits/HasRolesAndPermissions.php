<?php

namespace App\Traits;

use App\Models\Role;
use App\Models\Permission;

trait HasRolesAndPermissions
{
    public function roles()
    {
        // Spécifier explicitement le nom de la table pivot
        return $this->belongsToMany(Role::class, 'user_role');
    }

    // Assigner un rôle
    public function assignRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }
        
        if (!$this->roles->contains($role->id)) {
            $this->roles()->attach($role->id);
        }
        
        return $this;
    }

    // Retirer un rôle
    public function removeRole($role)
    {
        if (is_string($role)) {
            $role = Role::where('slug', $role)->firstOrFail();
        }
        
        $this->roles()->detach($role->id);
        return $this;
    }

    // Vérifier si l'utilisateur a un rôle
    public function hasRole($role)
    {
        if (is_string($role)) {
            return $this->roles->contains('slug', $role);
        }
        
        return $this->roles->contains($role);
    }

    // Vérifier si l'utilisateur a au moins un des rôles
    public function hasAnyRole($roles)
    {
        if (is_string($roles)) {
            $roles = explode('|', $roles);
        }
        
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }

    // Vérifier si l'utilisateur a tous les rôles
    public function hasAllRoles($roles)
    {
        if (is_string($roles)) {
            $roles = explode('|', $roles);
        }
        
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        
        return true;
    }

    // Vérifier si l'utilisateur a une permission
    public function hasPermission($permission)
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('slug', $permission)) {
                return true;
            }
        }
        
        return false;
    }

    // Obtenir toutes les permissions de l'utilisateur
    public function getAllPermissions()
    {
        return $this->roles->flatMap(function ($role) {
            return $role->permissions;
        })->unique('id');
    }

    public function isAdmin()
    {
        return $this->hasRole('admin');
    }

    public function isValidator()
    {
        return $this->hasRole('validator');
    }

    public function isCorrector()
    {
        return $this->hasRole('corrector');
    }

    public function isManager()
    {
        return $this->hasRole('manager');
    }

    public function isCustomer()
    {
        return $this->hasRole('customer');
    }

    public function hasHigherRoleThan($user)
    {
        $myHighestLevel = $this->roles->max('hierarchy_level') ?? 0;
        $theirHighestLevel = $user->roles->max('hierarchy_level') ?? 0;
        
        return $myHighestLevel > $theirHighestLevel;
    }

    public function getHighestHierarchyLevel()
    {
        return $this->roles->max('hierarchy_level') ?? 0;
    }
}