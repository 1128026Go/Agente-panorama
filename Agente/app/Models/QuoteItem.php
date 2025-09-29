<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo de ítems de una cotización.  Cada ítem incluye la descripción
 * del servicio, cantidad, precio unitario y total de la línea.
 */
class QuoteItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'description',
        'quantity',
        'unit_price',
        'tax_rate',
        'line_total',
    ];

    protected $casts = [
        'quantity'   => 'float',
        'unit_price' => 'float',
        'tax_rate'   => 'float',
        'line_total' => 'float',
    ];

    /**
     * Un ítem pertenece a una cotización.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}