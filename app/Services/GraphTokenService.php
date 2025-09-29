<?php

namespace App\Services;

/**
 * Servicio mínimo para obtener un token de Microsoft Graph.
 *
 * Este stub simplemente devuelve el token definido en la configuración
 * `services.graph.token`.  Para un entorno real deberás integrar
 * el flujo de autenticación de Microsoft (OAuth 2.0 app-only) y
 * refrescar el token según corresponda.
 */
class GraphTokenService
{
    public function getAppToken(): string
    {
        return config('services.graph.token');
    }
}