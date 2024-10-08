<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $primaryKey = 'item_id'; // Clé primaire
    protected $fillable = ['title', 'description', 'price'];

    public function shopItems()
    {
        return $this->hasMany(ShopItem::class);
    }
}
