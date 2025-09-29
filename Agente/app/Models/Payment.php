<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo que representa un pago relacionado con una cotización.
 *
 * Este modelo almacena información sobre la pasarela usada, el
 * identificador generado por la pasarela, el monto, la moneda,
 * el estado y un campo de payload para guardar información
 * adicional retornada por la pasarela.  Cada pago pertenece
 * a una cotización.
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'quote_id',
        'gateway',
        'gateway_ref',
        'amount',
        'currency',
        'status',
        'payload',
    ];

    protected $casts = [
        'amount'  => 'float',
        'payload' => 'array',
    ];

    /**
     * Un pago pertenece a una cotización.
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }
}