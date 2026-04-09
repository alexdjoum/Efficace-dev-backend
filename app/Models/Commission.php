<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'project_sold_id',
        'account_type_id',
        'rate',
        'commission_amount',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'commission_amount' => 'decimal:2',
    ];

    protected $appends = ['total_paid', 'remaining_amount'];

    public function projectSold()
    {
        return $this->belongsTo(ProjectSold::class);
    }

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function commercial()
    {
        return $this->belongsTo(User::class, 'account_type_id', 'account_type_id');
    }

    public function payments()
    {
        return $this->hasMany(PaymentSalesperson::class, 'commission_id');
    }

    public function getTotalPaidAttribute()
    {
        return PaymentSalesperson::where('commission_id', $this->id)->sum('amount_paid');
    }

    public function getRemainingAmountAttribute()
    {
        return $this->commission_amount - $this->total_paid;
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function paymentSalespersons()
    {
        return $this->hasMany(PaymentSalesperson::class);
    }
}