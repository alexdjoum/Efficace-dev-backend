<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $with = ['productable'];
    // auto generate reference number
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->reference = strtoupper(uniqid('PROD-', true));
        });
    }

    protected $fillable = ['reference', 'for_rent', 'for_sale', 'unit_price', 'total_price', 'description', 'status', 'published', 'productable_id', 'productable_type'];

    public function productable()
    {
        return $this->morphTo();
    }
}
