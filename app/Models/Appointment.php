<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'day',
        'hour',
        'user_id',
        'product_id',
        'status',
    ];

    const STATUS_PENDING = 'In pending';
    const STATUS_DONE = 'Done';

    protected $casts = [
        'day' => 'date',
        'hour' => 'datetime:H:i',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

}