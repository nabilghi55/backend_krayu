<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
   protected $fillable = [
    'title',
    'slug',
    'description',
    'product_image',
    'short_description',
    'shipping_returns',
    'related_products',
    'price',
    'compare_price',
    'is_featured',
    'sku',
    'track_qty',
    'qty',
    'status',
];
    use HasFactory;
}
