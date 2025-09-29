<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

/**
 * Servicio para interactuar con el calendario de Microsoft Graph.
 *
 * Se apoya en un `GraphTokenService` para obtener tokens de aplicación.
 * Permite crear eventos y buscar espacios disponibles en un rango
 * determinado.  En este stub las llamadas usan app-only tokens y
 * retornan la respuesta JSON de Graph.
 */
class GraphCalendarService
{
    public function __construct(private GraphTokenService $tokenService) {}

    /**
     * Crea un evento en la cuenta configurada.
     */
    public function createEvent(array $data)
    {
        $token = $this->tokenService->getAppToken();
        $user  = env('MSFT_CALENDAR_EMAIL');
        return Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$user}/events", $data)
            ->throw()
            ->json();
    }

    /**
     * Encuentra el primer espacio libre en los próximos 7 días entre las 8:00
     * y las 17:00.  Devuelve un arreglo con start y end en ISO8601 o null si
     * no hay espacios.
     */
    public function findAvailableSlot(int $durationMinutes = 60): ?array
    {
        $token = $this->tokenService->getAppToken();
        $user  = env('MSFT_CALENDAR_EMAIL');
        $tz    = 'America/Bogota';
        $start = Carbon::now($tz)->startOfDay();
        $end   = (clone $start)->addDays(7)->endOfDay();
        $payload = [
            'schedules' => [$user],
            'startTime' => [
                'dateTime' => $start->toIso8601String(),
                'timeZone' => $tz,
            ],
            'endTime' => [
                'dateTime' => $end->toIso8601String(),
                'timeZone' => $tz,
            ],
            'availabilityViewInterval' => 30,
        ];
        $resp = Http::withToken($token)
            ->post("https://graph.microsoft.com/v1.0/users/{$user}/calendar/getSchedule", $payload)
            ->throw()
            ->json();
        $items = $resp['value'][0]['scheduleItems'] ?? [];
        $cursorNow = Carbon::now($tz)->addHour()->ceilHour();
        for ($d = 0; $d < 7; $d++) {
            $day      = $cursorNow->copy()->startOfDay()->addDays($d);
            $dayStart = $day->copy()->setTime(8, 0);
            $dayEnd   = $day->copy()->setTime(17, 0);
            $busyToday = [];
            foreach ($items as $it) {
                $s = Carbon::parse($it['start']['dateTime'], $tz);
                $e = Carbon::parse($it['end']['dateTime'],   $tz);
                if ($e <= $dayStart || $s >= $dayEnd) continue;
                $busyToday[] = ['s' => max($s, $dayStart), 'e' => min($e, $dayEnd)];
            }
            usort($busyToday, fn ($a, $b) => $a['s'] <=> $b['s']);
            $cursor = $dayStart->copy();
            if ($d === 0 && $cursor < $cursorNow) {
                $cursor = $cursorNow->copy();
            }
            foreach ($busyToday as $b) {
                if ($cursor->diffInMinutes($b['s']) >= $durationMinutes) {
                    return [
                        'start' => $cursor->copy()->toIso8601String(),
                        'end'   => $cursor->copy()->addMinutes($durationMinutes)->toIso8601String(),
                    ];
                }
                if ($cursor < $b['e']) {
                    $cursor = $b['e']->copy();
                }
            }
            if ($cursor->diffInMinutes($dayEnd) >= $durationMinutes) {
                return [
                    'start' => $cursor->copy()->toIso8601String(),
                    'end'   => $cursor->copy()->addMinutes($durationMinutes)->toIso8601String(),
                ];
            }
        }
        return null;
    }
}