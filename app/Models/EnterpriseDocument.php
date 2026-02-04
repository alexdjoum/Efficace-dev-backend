<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EnterpriseDocument extends Model
{
    protected $fillable = [
        'user_id',
        'commercial_register',
        'immigration_certificate',
        'certificate_of_compliance',
        'approval',
        'patent',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}