<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'roles' => $this->getUserRoles(),
            'permissions' => $this->getUserPermissions(),
            'profile' => $this->userable,
        ];
    }

    protected function getUserRoles()
    {
        return $this->roles->map(function ($role) {
            return [
                'name' => $role->name,
                'display_name' => __('roles.' . $role->name),
            ];
        });
    }

    protected function getUserPermissions()
    {
        return $this->getAllPermissions()->map(function ($permission) {
            return [
                'name' => $permission->name,
                'display_name' => __('permissions.' . $permission->name),
            ];
        });
    }
}