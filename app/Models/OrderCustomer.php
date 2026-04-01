<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderCustomer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone_number',
        'budget',
        'localization',
        'land_area',
        'description',
        'type',
        'purchase_time',
        'building_type',
        'number_of_apartments',
        'function',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'land_area' => 'decimal:2',
        'number_of_apartments' => 'integer',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}