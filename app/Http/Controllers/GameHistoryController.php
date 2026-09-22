<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BaccaratHistory;
use App\Models\AndarbaharHistory;
use App\Models\RouletteHistory;
use App\Models\GameTable;
use App\Models\GameType;

class GameHistoryController extends Controller
{
    /**
     * In-scope games for the Virtuals system.
     * Format: 'KEY' => [ModelClass, 'db_table', 'view_partial_name']
     */
    private array $gameMap = [
        'BACCARAT'   => [BaccaratHistory::class,   'baccarat_history',   'baccarat'],
        'BAC'        => [BaccaratHistory::class,   'baccarat_history',   'baccarat'],
        'ANDARBAHAR' => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'AB'         => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'ROULETTE'   => [RouletteHistory::class,   'roulette_history',   'roulette'],
        'ROL'        => [RouletteHistory::class,   'roulette_history',   'roulette'],
    ];


    public function index(Request $request)
    {
        $tables    = GameTable::with('gameType')->where('status', 1)->get();
        $gameTypes = GameType::where('status', 1)->get();
        $records   = [];
        $selectedTable = null;
        $selectedGame  = $request->input('game');
        $tableId       = $request->input('table_id');
        $normalizedGame = null;

        if ($selectedGame && isset($this->gameMap[strtoupper($selectedGame)])) {
            $normalizedGame = $this->gameMap[strtoupper($selectedGame)][2];
        }

        $tabIds = [];

        if ($selectedGame && $tableId) {
            $req = Request::create('', 'GET', $request->all());
            $response      = $this->byTable($req, $selectedGame, (int) $tableId);
            $records       = $response->getData(true);
            $selectedTable = GameTable::with('gameType')->find($tableId);

            // Fetch distinct tab IDs for this table from the correct history table
            if (isset($this->gameMap[strtoupper($selectedGame)])) {
                $modelClass = $this->gameMap[strtoupper($selectedGame)][0];
                $tabIds = $modelClass::where('table_id', $tableId)
                    ->whereNotNull('tab_id')
                    ->where('tab_id', '!=', '')
                    ->distinct()
                    ->orderBy('tab_id')
                    ->pluck('tab_id')
                    ->toArray();
            }
        }

        return view('history.index', compact(
            'tables',
            'gameTypes',
            'records',
            'selectedTable',
            'selectedGame',
            'normalizedGame',
            'tabIds'
        ));
    }

    private function resolveModel(string $game): string
    {
        $key = strtoupper($game);
        abort_if(!isset($this->gameMap[$key]), 404, 'Unknown game type: ' . $game);
        return $this->gameMap[$key][0];
    }

    public function store(Request $request, string $game)
    {
        $model = $this->resolveModel($game);
        $data = $request->all();

        // Support multiple bet positions in format "banker:100,tie:200" or as array
        if (isset($data['bet_position']) && is_string($data['bet_position'])) {
            $data['bet_position'] = $this->parseKeyValueString($data['bet_position']);
        }

        // Support side_win in format "player_pair,lucky6" or as array
        if (isset($data['side_win']) && is_string($data['side_win'])) {
            $data['side_win'] = array_map('trim', explode(',', $data['side_win']));
        }

        $record = $model::create($data);
        return response()->json(['success' => true, 'id' => $record->id], 201);
    }

    private function parseKeyValueString(string $str): array
    {
        if (empty($str)) return [];

        $parts = explode(',', $str);
        $result = [];
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                [$key, $val] = explode(':', $part, 2);
                $result[trim($key)] = trim($val);
            } else {
                $result[] = trim($part);
            }
        }
        return $result;
    }

    public function byTable(Request $request, string $game, int $id)
    {
        $model = $this->resolveModel($game);

        $gameNo = $request->input('game_no');

        // If no game_no, get the latest one from the table that matches filters
        if (!$gameNo) {
            $latest = $model::where('table_id', $id)
                ->when($request->tab_id, fn($q) => $q->where('tab_id', $request->tab_id))
                ->when($request->date,   fn($q) => $q->whereDate('date_time', $request->date))
                ->when($request->winner, fn($q) => $q->where('winner', $request->winner))
                ->orderBy('date_time', 'desc')
                ->first();
            $gameNo = $latest ? $latest->game_no : null;
        }

        $query = $model::where('table_id', $id)
            ->when($gameNo, fn($q) => $q->where('game_no', $gameNo))
            ->when($request->tab_id, fn($q) => $q->where('tab_id', $request->tab_id))
            ->when($request->date,   fn($q) => $q->whereDate('date_time', $request->date))
            ->when($request->winner, fn($q) => $q->where('winner', $request->winner))
            ->orderBy('date_time', 'desc');

        $data = $query->get();

        // Get navigation list (distinct game_nos ordered by time) that match filters
        $gameNos = $model::where('table_id', $id)
            ->when($request->tab_id, fn($q) => $q->where('tab_id', $request->tab_id))
            ->when($request->date,   fn($q) => $q->whereDate('date_time', $request->date))
            ->when($request->winner, fn($q) => $q->where('winner', $request->winner))
            ->select('game_no')
            ->groupBy('game_no')
            ->orderByRaw('MAX(date_time) DESC')
            ->pluck('game_no')
            ->toArray();

        $currentIndex = array_search($gameNo, $gameNos);
        $prevGameNo   = ($currentIndex !== false && isset($gameNos[$currentIndex + 1])) ? $gameNos[$currentIndex + 1] : null;
        $nextGameNo   = ($currentIndex !== false && $currentIndex > 0) ? $gameNos[$currentIndex - 1] : null;

        return response()->json([
            'data'         => $data,
            'current_page' => $currentIndex !== false ? $currentIndex + 1 : 1,
            'last_page'    => count($gameNos),
            'total'        => count($gameNos),
            'from'         => $currentIndex !== false ? $currentIndex + 1 : 0,
            'to'           => $currentIndex !== false ? $currentIndex + 1 : 0,
            'per_page'     => 1,
            'game_no'      => $gameNo,
            'prev_game_no' => $prevGameNo,
            'next_game_no' => $nextGameNo,
            'all_game_nos' => $gameNos,
        ]);
    }

    public function byTab(Request $request, string $game, string $tabId)
    {
        $model = $this->resolveModel($game);
        $data = $model::where('tab_id', $tabId)
            ->when($request->table_id, fn($q) => $q->where('table_id', $request->table_id))
            ->orderBy('date_time', 'desc')
            ->paginate($request->per_page ?? 50);

        return response()->json($data);
    }

    public function show(string $game, int $recordId)
    {
        $model = $this->resolveModel($game);
        return response()->json($model::findOrFail($recordId));
    }
}
