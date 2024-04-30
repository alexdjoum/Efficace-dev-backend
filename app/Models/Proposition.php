<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proposition extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $with = ['customer', 'proposable', 'contract'];

    protected $fillable = ['customer_id', 'proposable_id', 'proposable_type', 'price', 'status', 'description'];

    public function proposable()
    {
        return $this->morphTo();
    }

    public function contract()
    {
        return $this->morphOne(Contract::class, 'contractable');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
