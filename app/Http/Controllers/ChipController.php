<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Chip;

class ChipController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 1); // default to Active

        $chips = Chip::latest()
            ->where('status', $status)
            ->paginate(5)
            ->appends(['status' => $status]); // keeps filter across pagination

        return view('chips.index', compact('chips', 'status'));
    }


    public function store(Request $request)
    {

        $request->validate([
            'preset_name' => 'required|string|unique:chips,preset_name',
            'chip_1_value' => 'required|numeric',
            'chip_2_value' => 'required|numeric',
            'chip_3_value' => 'required|numeric',
            'chip_4_value' => 'required|numeric',
            'chip_5_value' => 'required|numeric',
            'base_value' => 'required|numeric'
        ]);

        Chip::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Chip preset created'
        ]);
    }


    public function update(Request $request, $id)
    {

        $request->validate([
            'preset_name' => 'required|string|unique:chips,preset_name,'.$id,
            'chip_1_value' => 'required|numeric',
            'chip_2_value' => 'required|numeric',
            'chip_3_value' => 'required|numeric',
            'chip_4_value' => 'required|numeric',
            'chip_5_value' => 'required|numeric',
            'base_value' => 'required|numeric'
        ]);

        Chip::findOrFail($id)->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Chip preset updated'
        ]);
    }


    public function destroy($id)
    {

        Chip::findOrFail($id)->update([
            'status' => 0
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Preset deleted'
        ]);
    }

    public function restore($id)
    {
        Chip::findOrFail($id)->update([
            'status' => 1
        ]);
        return response()->json([
            'success' => true,
            'message' => 'Preset restored'
        ]);
    }


    public function show($id)
    {
        return Chip::findOrFail($id);
    }
}
