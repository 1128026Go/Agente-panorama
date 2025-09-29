<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;

class LaiaCalendarController extends Controller
{
    public function schedule(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string',
            'start' => 'required|date',
            'duration_minutes' => 'required|integer',
            'attendees' => 'array',
        ]);

        $start = Carbon::parse($data['start']);
        $event = [
            'id' => 'evt_123',
            'title' => $data['title'],
            'start' => $start->toIso8601String(),
            'end' => $start->copy()->addMinutes($data['duration_minutes'])->toIso8601String(),
            'join_url' => 'https://meeting.example/join/abc',
        ];

        return response()->json([
            'ok' => true,
            'message' => 'Evento agendado',
            'data' => ['event' => $event],
        ]);
    }
}