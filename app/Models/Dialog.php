<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo para almacenar cada intercambio de diálogo con el chatbot.
 *
 * Se almacenan tanto la consulta del usuario como la respuesta de la IA,
 * además del contexto de la sesión y cualquier metadato adicional.
 */
class Dialog extends Model
{
    use HasFactory;

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $fillable = [
        'user_query',
        'response',
        'session_context',
        'meta',
    ];

    /**
     * Conversión automática de atributos.
     */
    protected $casts = [
        'session_context' => 'array', // Esta línea evita el error de JSON -> array
        'meta'            => 'array',
    ];
}