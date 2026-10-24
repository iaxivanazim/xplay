<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GameType;

class GameTypeController extends Controller
{
    public function index()
    {
        $types = GameType::latest()->get();
        return view('game_types.index', compact('types'));
    }

    public function create()
    {
        return view('game_types.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:game_types,code',
            'description' => 'nullable|string',
        ]);

        GameType::create($request->all());

        return redirect()
            ->route('game_types.index')
            ->with('success', 'Game Type created successfully.');
    }

    public function edit($id)
    {
        $type = GameType::findOrFail($id);
        return view('game_types.edit', compact('type'));
    }

    public function update(Request $request, $id)
    {
        $type = GameType::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:game_types,code,' . $id,
            'description' => 'nullable|string',
        ]);

        $type->update($request->all());

        return redirect()
            ->route('game_types.index')
            ->with('success', 'Game Type updated successfully.');
    }

    public function destroy($id)
    {
        $type = GameType::findOrFail($id);

        if ($type->tables()->exists()) {
            return response()->json([
                'message' => 'Cannot delete. Game Type is assigned to tables.'
            ], 400);
        }

        $type->delete();

        return response()->json([
            'message' => 'Game Type deleted successfully.'
        ]);
    }

    public function apiIndex()
    {
        $gameTypes = GameType::where('status', 1)->get();

        return response()->json([
            'success' => true,
            'count'   => $gameTypes->count(),
            'data'    => $gameTypes
        ]);
    }
}
