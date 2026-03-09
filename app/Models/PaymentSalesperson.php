<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSalesperson extends Model
{
    protected $table = 'payment_salespersons';

    protected $fillable = [
        'commission_id',
        'commercial_id',
        'amount_paid',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
    ];

    public function commission()
    {
        return $this->belongsTo(Commission::class);
    }

    public function commercial()
    {
        return $this->belongsTo(User::class, 'commercial_id');
    }
}