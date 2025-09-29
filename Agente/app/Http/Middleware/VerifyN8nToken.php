<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Middleware para validar el token de llamadas internas provenientes
 * de la instancia n8n.  Las solicitudes deben incluir el header
 * `X-Internal-Token` con el valor configurado en
 * `config('services.laia_n8n.token')`.  Si el token no coincide, se
 * devuelve un 401.
 */
class VerifyN8nToken
{
    public function handle(Request $request, Closure $next)
    {
        $headerToken  = $request->header('X-Internal-Token');
        $tokenPresent = filled($headerToken);
        Log::info('VerifyN8nToken: incoming', mask_sensitive([
            'path' => $request->path(),
            'token_present' => $tokenPresent,
            'token' => $headerToken,
        ]));

        $expected = config('services.laia_n8n.token');
        if (!$tokenPresent || $headerToken !== $expected) {
            Log::warning('VerifyN8nToken: unauthorized', mask_sensitive([
                'path' => $request->path(),
                'token_present' => $tokenPresent,
                'token' => $headerToken,
                'expected_configured' => filled($expected),
            ]));
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        return $next($request);
    }
}