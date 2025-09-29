<?php

namespace App\Http\Controllers;

use App\Services\CalculationApiClient;
use Illuminate\Http\Request;

class LaiaCalcController extends Controller
{
    public function run(Request $request, CalculationApiClient $client)
    {
        $data = $request->validate([
            'service' => 'required|string',
            'inputs' => 'required|array',
        ]);

        $result = $client->run($data['service'], $data['inputs']);
        $ok = (bool) ($result['ok'] ?? false);
        $status = $ok ? 200 : 422;
        $message = $ok
            ? 'Calculo ejecutado correctamente'
            : ($result['details'] ?? 'No fue posible completar el calculo');

        return response()->json([
            'ok' => $ok,
            'message' => $message,
            'data' => [
                'result' => $result,
            ],
        ], $status);
    }
}