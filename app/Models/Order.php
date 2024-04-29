<?php

namespace App\Models;

use App\Traits\CustomLogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory, CustomLogsActivity;

    protected $with = ['product', 'customer'];

    protected $fillable = ['product_id', 'customer_id', 'unit_price', 'total_price', 'status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
