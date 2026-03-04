<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTypeLot extends Model
{
    protected $fillable = [
        'account_type_id',
        'lot_id',
    ];

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function lot()
    {
        return $this->belongsTo(Lot::class);
    }
}