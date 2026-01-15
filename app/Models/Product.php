<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $with = ['productable'];
    
    public static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->reference = strtoupper(uniqid('PROD-'));
        });
    }

    protected $fillable = ['reference', 'for_rent', 'for_sale', 'unit_price', 'total_price', 'description', 'status', 'published_at', 'productable_id', 'productable_type'];

    public function productable()
    {
        return $this->morphTo();
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function proposedProducts()
    {
        return $this->belongsToMany(
            Product::class,
            'proposed_products',
            'product_id',
            'proposed_product_id'
        )->withTimestamps();
    }

    public function proposingProducts()
    {
        return $this->belongsToMany(
            Product::class,
            'proposed_products',
            'proposed_product_id',
            'product_id'
        )->withTimestamps();
    }
}
