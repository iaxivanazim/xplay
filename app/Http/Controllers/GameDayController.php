<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameDay;

class GameDayController extends Controller
{
    public function start()
    {
        if (GameDay::current()) {
            return response()->json([
                'message' => 'A game day is already active.'
            ], 400);
        }

        $now = now();

        GameDay::create([
            'gaming_date' => $now->toDateString(),
            'started_at' => $now,
            'started_by' => auth()->guard('web')->user()->id,
        ]);

        return response()->json([
            'message' => 'Game Day started successfully.'
        ]);
    }

    public function close($id)
    {
        $gameDay = GameDay::findOrFail($id);

        if ($gameDay->is_closed) {
            return response()->json([
                'message' => 'Game Day already closed.'
            ], 400);
        }

        $end = now();
        $duration = $gameDay->started_at->diffInHours($end);

        $gameDay->update([
            'ended_at' => $end,
            'duration_hours' => $duration,
            'is_closed' => true,
            'closed_by' => auth()->guard('web')->user()->id,
        ]);

        return response()->json([
            'message' => 'Game Day closed successfully.'
        ]);
    }

    public function current()
    {
        $gameDay = GameDay::where('is_closed', false)->first();

        if (!$gameDay) {
            return response()->json([
                'status' => 'no_active_game_day'
            ], 200);
        }

        return response()->json([
            'status' => 'active',
            'data' => [
                'id' => $gameDay->id,
                'gaming_date' => $gameDay->gaming_date,
                'started_at' => $gameDay->started_at,
            ]
        ]);
    }
    
}
