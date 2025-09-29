<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para invocar el microservicio de cálculos de ingeniería.
 *
 * Este cliente lee la URL y el token de la API desde el archivo
 * de configuración `config/calculation.php` y realiza una llamada
 * POST con el identificador del servicio y los datos de entrada.
 */
class CalculationApiClient
{
    protected string $baseUrl;
    protected ?string $token;

    public function __construct()
    {
        $this->baseUrl = (string) config('calculation.api_url');
        $this->token   = config('calculation.api_token');
    }

    /**
     * Ejecuta un cálculo remoto.
     */
    public function run(string $serviceId, array $inputs): array
    {
        if (empty($this->baseUrl)) {
            Log::error('CALC_API_ERROR', ['message' => 'La URL de la API de cálculo no está configurada.']);
            return ['ok' => false, 'details' => 'La API de cálculo no está configurada.'];
        }
        $apiServiceId = config('laia.api_service_map.' . $serviceId, $serviceId);
        $payload = [
            'service' => $apiServiceId,
            'inputs'  => $inputs,
            'token'   => $this->token,
        ];
        try {
            Log::info('Llamando a la API de cálculo', mask_sensitive($payload));
            $response = Http::timeout(30)->post($this->baseUrl, $payload);
            if ($response->successful()) {
                $responseData          = $response->json();
                $responseData['ok']    = ($responseData['status'] ?? 'error') === 'ok';
                return $responseData;
            }
            Log::warning('CALC_API_REQUEST_FAILED', mask_sensitive([
                'status' => $response->status(),
                'body'   => $response->body(),
            ]));
            return [
                'ok'      => false,
                'details' => 'La API de cálculo devolvió un error.',
                'status'  => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('CALC_API_CONNECTION_ERROR', mask_sensitive([
                'message'    => substr($e->getMessage(), 0, 200),
                'exception'  => get_class($e),
            ]));
            return ['ok' => false, 'details' => 'No se pudo conectar con la API de cálculo.'];
        }
    }
}