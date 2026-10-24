<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Theme;

class ThemeController extends Controller
{
    public function index()
    {
        $themes = Theme::latest()->get();
        return view('themes.index', compact('themes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:themes,code',
            'primary_color' => 'nullable|string',
            'secondary_color' => 'nullable|string',
        ]);

        Theme::create($request->all());

        return redirect()
            ->route('themes.index')
            ->with('success', 'Theme created successfully.');
    }

    public function destroy($id)
    {
        $theme = Theme::findOrFail($id);

        if ($theme->tables()->exists()) {
            return response()->json([
                'message' => 'Cannot delete. Theme is assigned to tables.'
            ], 400);
        }

        $theme->delete();

        return response()->json([
            'message' => 'Theme deleted successfully.'
        ]);
    }
}
