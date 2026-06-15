<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $guarded = [];
    protected $appends = ['display_stock', 'stock_status_class'];
    protected $with = ['category']; // Selalu load kategori agar isServiceProduct bekerja di JSON

    // Helper untuk mendapatkan harga berdasarkan tipe customer
    public function getPriceForCustomer($type)
    {
        return match ($type) {
            'agen1' => $this->price_agent1,
            'agen2' => $this->price_agent2,
            default => $this->price_general,
        };
    }

    public function category() { return $this->belongsTo(Category::class); }
    
    public function transactionItems() { return $this->hasMany(TransactionItem::class); }
    
    /**
     * Check if this product belongs to a service category (unlimited stock)
     */
    public function isServiceProduct()
    {
        return $this->category && $this->category->isServiceCategory();
    }
    
    /**
     * Get display stock for service products
     */
    public function getDisplayStockAttribute()
    {
        if ($this->isServiceProduct()) {
            return '∞'; // Infinity symbol for unlimited stock
        }
        
        return $this->stock;
    }
    
    /**
     * Get stock status class for display
     */
    public function getStockStatusClassAttribute()
    {
        if ($this->isServiceProduct()) {
            return 'text-green-600'; // Green for unlimited stock
        }
        
        if ($this->stock <= 5) {
            return 'text-red-600';
        } elseif ($this->stock <= 20) {
            return 'text-yellow-600';
        } else {
            return 'text-green-600';
        }
    }
}
