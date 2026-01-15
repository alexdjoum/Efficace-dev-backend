<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposedProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'proposed_product_id',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function proposedProduct()
    {
        return $this->belongsTo(Product::class, 'proposed_product_id');
    }
}