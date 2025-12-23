<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Spatie\Activitylog\Models\Activity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use App\Traits\HasRolesAndPermissions; 

class User extends Authenticatable implements HasMedia
{
    use HasApiTokens, 
        HasFactory, 
        Notifiable, 
        HasRolesAndPermissions, 
        InteractsWithMedia, 
        AuthenticationLoggable;

    /**
     * Les champs autorisés au mass-assignment
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
     * Champs à cacher lors de la sérialisation
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'media',
    ];

    /**
     * Relations à charger automatiquement
     *
     * @var array
     */
    protected $with = ['roles', 'authentications', 'userable'];

    /**
     * Champs ajoutés à la sérialisation
     *
     * @var array
     */
    protected $appends = [
        "profile",
        "all_permissions",
        "logs"
    ];

    /**
     * Casts
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Relation polymorphique
     */
    public function userable()
    {
        return $this->morphTo();
    }

    /**
     * URL du profil
     */
    public function getProfileAttribute()
    {
        return $this->getFirstMediaUrl("profile");
    }

    /**
     * Permissions de l'utilisateur
     */
    public function getAllPermissionsAttribute()
    {
        return $this->getAllPermissions();
    }

    /**
     * Logs de l'utilisateur
     */
    public function getLogsAttribute()
    {
        return Activity::query()->where("causer_id", $this->id)->get();
    }

    /**
     * Collections de médias
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('profile')
            ->useFallbackUrl(asset('/assets/images/no_profile.jpeg'))
            ->useFallbackPath(public_path('/assets/images/no_profile.jpeg'))
            ->singleFile();
    }
}