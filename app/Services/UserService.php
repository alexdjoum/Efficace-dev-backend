<?php

namespace App\Services;

use App\Models\User;

class UserService
{
    public function create(array $data, $on)
    {
        // create user model
        $user = User::query()->make($data);

        $on->user()->save($user);

        // add profile image if exists
        if (isset($data['profile'])) {
            $user->addMedia($data['profile'])
                ->toMediaCollection('profile');
        }

        // sync roles if exists
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // sync permissions if exists
        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        // dispatch event for sending email

        return $user;
    }

    public function update(User $user, array $data)
    {
        $user->update($data);

        if (isset($data['profile'])) {
            $user->addMedia($data['profile'])
                ->toMediaCollection('profile');
        }

        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        if (isset($data['permissions'])) {
            $user->syncPermissions($data['permissions']);
        }

        return $user;
    }
}
