<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CalculosController extends Controller
{
    public function calcular(Request $request)
    {
        // Aquí iría tu lógica de cálculo real.
        // Por ahora, solo validamos y devolvemos un resultado de ejemplo.
        $validated = $request->validate([
            'service' => 'required|string',
            'payload' => 'required|array',
        ]);

        return response()->json([
            'ok' => true,
            'service' => $validated['service'],
            'results' => [
                'phf' => 0.95, // Valor de ejemplo
                'hmd' => 1500, // Valor de ejemplo
            ],
            'notes' => 'Cálculo ejecutado exitosamente desde Laravel.'
        ]);
    }
}
