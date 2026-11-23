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
    private array $gameMap = [
        'BACCARAT'    => [BaccaratHistory::class,   'baccarat_history',   'baccarat'],
        'BAC'         => [BaccaratHistory::class,   'baccarat_history',   'baccarat'],
        'ANDARBAHAR'  => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'ANDAR_BAHAR' => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'ANDAR-BAHAR' => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'AB'          => [AndarbaharHistory::class, 'andarbahar_history', 'andarbahar'],
        'ROULETTE'    => [RouletteHistory::class,   'roulette_history',   'roulette'],
        'ROU'         => [RouletteHistory::class,   'roulette_history',   'roulette'],
        'ROL'         => [RouletteHistory::class,   'roulette_history',   'roulette'],
    ];

    public function index(Request $request)
    {
        $tables    = GameTable::with('gameType')->where('status', 1)->get();
        $gameTypes = GameType::where('status', 1)->get();
        $records   = [];
        $selectedTable = null;
        $selectedGame  = $request->input('game');
        $tableId       = $request->input('table_id', $request->input('tab', $request->input('tab_id')));
        $normalizedGame = null;

        if ($selectedGame && isset($this->gameMap[strtoupper($selectedGame)])) {
            $normalizedGame = $this->gameMap[strtoupper($selectedGame)][2];
        }

        if ($selectedGame && $tableId) {
            $req = Request::create('', 'GET', $request->all());
            $response      = $this->byTable($req, $selectedGame, (int) $tableId);
            $records       = $response->getData(true);
            $selectedTable = GameTable::with('gameType')->find($tableId);
        }

        return view('history.index', compact(
            'tables',
            'gameTypes',
            'records',
            'selectedTable',
            'selectedGame',
            'normalizedGame'
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

        // Parse card fields if passed as comma strings or JSON strings
        foreach (['player_cards', 'banker_cards', 'andar_cards', 'bahar_cards'] as $cardField) {
            if (isset($data[$cardField]) && is_string($data[$cardField])) {
                $trimmed = trim($data[$cardField]);
                if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
                    $decoded = json_decode($trimmed, true);
                    if (is_array($decoded)) {
                        $data[$cardField] = $decoded;
                    }
                } else {
                    $data[$cardField] = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
                }
            }
        }

        // Ensure date_time is always populated
        if (empty($data['date_time'])) {
            $data['date_time'] = now();
        }

        // Fallback game_no / round_no
        if (empty($data['game_no'])) {
            $data['game_no'] = $data['round_no'] ?? (strtoupper(substr($game, 0, 3)) . '-' . time());
        }

        // Bidirectional table_id and tab_id resolution
        if (!empty($data['table_id']) && empty($data['tab_id'])) {
            $tab = GameTable::find($data['table_id']);
            if ($tab) {
                $data['tab_id'] = (string)($tab->tab_number ?? $tab->id);
            }
        } elseif (empty($data['table_id']) && !empty($data['tab_id'])) {
            $tab = GameTable::where('tab_number', $data['tab_id'])
                ->orWhere('id', $data['tab_id'])
                ->orWhere('table_name', $data['tab_id'])
                ->first();
            if ($tab) {
                $data['table_id'] = $tab->id;
            }
        }
        if (empty($data['table_id'])) {
            $data['table_id'] = 1;
        }

        // Normalize winner string
        if (isset($data['winner']) && is_string($data['winner'])) {
            $w = strtolower(trim($data['winner']));
            if ($w === 'p') $data['winner'] = 'player';
            elseif ($w === 'b' && in_array(strtolower($game), ['baccarat', 'bac'])) $data['winner'] = 'banker';
            elseif ($w === 't') $data['winner'] = 'tie';
            elseif ($w === 'a') $data['winner'] = 'andar';
            elseif ($w === 'b' && in_array(strtolower($game), ['andarbahar', 'ab', 'andar_bahar', 'andar-bahar'])) $data['winner'] = 'bahar';
        }

        // Set numeric and array defaults
        $data['bet_amount']     = $data['bet_amount'] ?? 0;
        $data['win_amount']     = $data['win_amount'] ?? 0;
        $data['current_credit'] = $data['current_credit'] ?? 0;
        if (!isset($data['bet_position'])) {
            $data['bet_position'] = [];
        }

        if ($model === RouletteHistory::class) {
            if (isset($data['winning_number']) && empty($data['winning_colour'])) {
                $data['winning_colour'] = \App\Services\RouletteEngine::getColor((int)$data['winning_number']);
            }
            if (empty($data['roulette_type'])) {
                $data['roulette_type'] = 'European';
            }
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
        $tab   = GameTable::find($id);

        $gameNo = $request->input('game_no');
        
        $applyWinnerFilter = function ($q) use ($request, $model) {
            if (!$request->winner) return;
            if ($model === RouletteHistory::class) {
                $w = strtolower($request->winner);
                if (is_numeric($w)) {
                    $q->where('winning_number', (int)$w);
                } else {
                    $q->where('winning_colour', $w);
                }
            } else {
                $w = strtolower($request->winner);
                $q->where(function ($sub) use ($w) {
                    $sub->whereRaw('LOWER(winner) = ?', [$w]);
                    if ($w === 'player') $sub->orWhere('winner', 'P');
                    if ($w === 'banker') $sub->orWhere('winner', 'B');
                    if ($w === 'tie')    $sub->orWhere('winner', 'T');
                    if ($w === 'andar')  $sub->orWhere('winner', 'A');
                    if ($w === 'bahar')  $sub->orWhere('winner', 'B');
                });
            }
        };

        $applyTabScope = function ($q) use ($id, $tab) {
            $q->where(function ($sub) use ($id, $tab) {
                $sub->where('table_id', $id);
                if ($tab) {
                    $sub->orWhere('tab_id', (string)$id);
                    if ($tab->tab_number) {
                        $sub->orWhere('tab_id', (string)$tab->tab_number);
                    }
                    if ($tab->table_name) {
                        $sub->orWhere('tab_id', (string)$tab->table_name);
                    }
                }
            });
        };

        // If no game_no, get the latest one from the table that matches filters
        if (!$gameNo) {
            $latestQuery = $model::query();
            $applyTabScope($latestQuery);
            if ($request->tab_id) {
                $latestQuery->where('tab_id', $request->tab_id);
            }
            if ($request->date) {
                $latestQuery->whereDate('date_time', $request->date);
            }
            $applyWinnerFilter($latestQuery);
            $latest = $latestQuery->orderBy('date_time', 'desc')->first();
            $gameNo = $latest ? $latest->game_no : null;
        }

        $query = $model::query();
        $applyTabScope($query);
        if ($gameNo) {
            $query->where('game_no', $gameNo);
        }
        if ($request->tab_id) {
            $query->where('tab_id', $request->tab_id);
        }
        if ($request->date) {
            $query->whereDate('date_time', $request->date);
        }
        $applyWinnerFilter($query);
        $data = $query->orderBy('date_time', 'desc')->get();

        // Get navigation list (distinct game_nos ordered by time) that match filters
        $gameNosQuery = $model::query();
        $applyTabScope($gameNosQuery);
        if ($request->tab_id) {
            $gameNosQuery->where('tab_id', $request->tab_id);
        }
        if ($request->date) {
            $gameNosQuery->whereDate('date_time', $request->date);
        }
        $applyWinnerFilter($gameNosQuery);
        $gameNos = $gameNosQuery->select('game_no')
            ->groupBy('game_no')
            ->orderByRaw('MAX(date_time) DESC')
            ->pluck('game_no')
            ->toArray();

        $currentIndex = array_search($gameNo, $gameNos);
        $prevGameNo = ($currentIndex !== false && isset($gameNos[$currentIndex + 1])) ? $gameNos[$currentIndex + 1] : null;
        $nextGameNo = ($currentIndex !== false && $currentIndex > 0) ? $gameNos[$currentIndex - 1] : null;

        // Wrap in a structure similar to pagination for compatibility
        return response()->json([
            'data'         => $data,
            'current_page' => $currentIndex !== false ? $currentIndex + 1 : 1,
            'last_page'    => count($gameNos),
            'total'        => count($gameNos), // Total number of games
            'from'         => $currentIndex !== false ? $currentIndex + 1 : 0,
            'to'           => $currentIndex !== false ? $currentIndex + 1 : 0,
            'per_page'     => 1,
            'game_no'      => $gameNo,
            'prev_game_no' => $prevGameNo,
            'next_game_no' => $nextGameNo,
            'all_game_nos' => $gameNos
        ]);
    }

    public function byTab(Request $request, string $game, string $tabId)
    {
        $model = $this->resolveModel($game);
        $tab   = GameTable::where('tab_number', $tabId)
            ->orWhere('id', $tabId)
            ->orWhere('table_name', $tabId)
            ->first();

        $data = $model::where(function ($q) use ($tabId, $tab) {
                $q->where('tab_id', $tabId);
                if ($tab) {
                    $q->orWhere('table_id', $tab->id)
                      ->orWhere('tab_id', (string)$tab->tab_number)
                      ->orWhere('tab_id', (string)$tab->table_name);
                }
            })
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
