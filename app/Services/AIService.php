<?php

namespace App\Services;

/**
 * Servicio para detectar servicios solicitados a partir de consultas
 * del usuario mediante coincidencia difusa.  Este servicio se usa
 * como utilitario en AgentService y otros componentes.
 */
class AIService
{
    /**
     * Verifica si una cadena coincide aproximadamente con alguna de las
     * palabras clave dadas.  Utiliza coincidencia directa y la
     * distancia de Levenshtein sobre las palabras.
     */
    private function isFuzzyMatch(string $query, array $keywords, int $maxDistance = 2): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($query, $keyword)) {
                return true;
            }
        }
        $queryWords = explode(' ', preg_replace('/[^a-z0-9 ]/i', '', $query));
        foreach ($queryWords as $word) {
            if (empty($word)) continue;
            foreach ($keywords as $keyword) {
                if (levenshtein($word, $keyword) <= $maxDistance) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Busca un servicio dentro de una consulta textual y devuelve un
     * arreglo con el código y el nombre del servicio.  Si no se
     * encuentra, devuelve null.
     */
    public function findServiceInQuery(string $query): ?array
    {
        $query = strtolower($query);
        $serviceMap = [
            'SVC_ANDEN'     => ['cierre de anden'],
            'SVC_DESCARGUE' => ['descargue de materiales', 'cargue y descargue'],
            'SVC_VOLQUETA'  => ['entrada y salida de volquetas', 'volqueta'],
            'SVC_COLAS'     => ['analisis de colas', 'colas'],
            'SVC_VEH'       => ['capacidad vehicular', 'vehicular'],
            'SVC_PED'       => ['capacidad peatonal', 'peatonal', 'anden'],
            'SVC_AFORO'     => ['aforos', 'conteo'],
        ];
        foreach ($serviceMap as $code => $keywords) {
            if ($this->isFuzzyMatch($query, $keywords)) {
                $serviceName = config("laia.services.{$code}.name", 'Servicio Desconocido');
                return ['code' => $code, 'name' => $serviceName];
            }
        }
        if (str_contains($query, 'capacidad')) {
            return ['code' => 'SVC_VEH', 'name' => 'Capacidad Vehicular'];
        }
        return null;
    }
}