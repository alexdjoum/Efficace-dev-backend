<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'userable_type',
        'userable_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'media',
    ];

    protected $with = ['roles'];

    protected $appends = [
        "profile",
        "all_permissions"
    ];

    public function getAllPermissionsAttribute()
    {
        return $this->getAllPermissions();
    }

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function userable()
    {
        return $this->morphTo();
    }

    public function getProfileAttribute()
    {
        return $this->getFirstMediaUrl("profile");
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile')
            ->useFallbackUrl(asset('/assets/images/no_profile.jpeg'))
            ->useFallbackPath(public_path('/assets/images/no_profile.jpeg'))
            ->singleFile();
    }
}
