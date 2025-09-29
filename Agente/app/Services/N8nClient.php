<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Cliente simple para llamar a flujos de n8n mediante webhook.
 *
 * Lee la URL base y las credenciales de autenticación básica desde
 * `config/services.php` y envía las solicitudes con el token
 * interno necesario para autorizar las llamadas entrantes en Laravel.
 */
class N8nClient
{
    private string $baseUrl;
    private ?string $username;
    private ?string $password;
    private ?string $token;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.n8n.base_url'), '/');
        $this->username = config('services.n8n.user');
        $this->password = config('services.n8n.password');
        $this->token    = config('services.laia_n8n.token');
    }

    /**
     * Realiza una llamada POST al endpoint especificado de n8n.
     *
     * @param string $endpoint Ruta relativa del webhook en n8n
     * @param array  $payload  Datos a enviar
     * @return array Respuesta JSON decodificada
     */
    public function call(string $endpoint, array $payload): array
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        $request = Http::withBasicAuth($this->username, $this->password)
            ->withHeaders(['X-Internal-Token' => $this->token])
            ->timeout(20);
        return $request->post($url, $payload)->json();
    }
}