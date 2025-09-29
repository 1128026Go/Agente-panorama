<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware para verificar la clave de agente en llamadas protegidas.
 *
 * Las llamadas protegidas deben enviar la cabecera `X-AGENT-KEY` o
 * `Authorization: Bearer <token>` con el valor definido en la variable
 * de entorno `AGENTE_API_TOKEN`.  Si no coincide, se responde 401.
 */
class VerifyAgentKey
{
    public function handle(Request $request, Closure $next)
    {
        $token = env('AGENTE_API_TOKEN');
        if (!$token) {
            return response()->json(['ok' => false, 'error' => 'token_not_set'], 500);
        }
        $h1     = (string) $request->header('X-AGENT-KEY');        // token directo
        $h2     = (string) $request->header('Authorization');      // "Bearer xxx"
        $bearer = 'Bearer ' . $token;
        $ok     = hash_equals($token, $h1) || hash_equals($bearer, $h2);
        if (!$ok) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }
        return $next($request);
    }
}