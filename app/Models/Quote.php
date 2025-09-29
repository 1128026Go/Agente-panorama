<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo de una cotización.  Almacena la información general del cliente
 * y los montos totales.  Cada cotización puede tener muchos ítems.
 */
class Quote extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'customer_name',
        'customer_email',
        'status',
        'valid_until',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'meta',
    ];

    protected $casts = [
        'valid_until'    => 'date',
        'meta'           => 'array',
        'subtotal'       => 'float',
        'tax_total'      => 'float',
        'discount_total' => 'float',
        'total'          => 'float',
    ];

    /**
     * Una cotización tiene muchos ítems asociados.
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}