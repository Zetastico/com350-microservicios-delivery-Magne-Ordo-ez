<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class OrderItem extends Model

{
protected $fillable = ['order_id', 'dish_id', 'dish_name', 'unit_price', 'quantity',
'subtotal'];
protected $casts = [
'dish_id' => 'integer',
'quantity' => 'integer',
'unit_price' => 'decimal:2',
'subtotal' => 'decimal:2',
];
public function order(): BelongsTo
{
return $this->belongsTo(Order::class);
}
}