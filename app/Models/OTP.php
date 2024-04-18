<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OTP extends Model
{
    use HasFactory;

    protected $table = "otps";

    protected $fillable = ['email', 'code'];

    public static function boot()
    {
        parent::boot();

        static::creating(function($model){
            $model->code = random_int(100000, 999999);
        });
    }
    public function isExpire(): bool
    {
        if ($this->created_at < now()->subHour()) {
            $this->delete();
        }
        return $this->created_at < now()->subHour();
    }
}