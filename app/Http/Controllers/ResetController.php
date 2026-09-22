<?php

namespace App\Http\Controllers;

use App\Services\ResetService;
use Illuminate\Http\Request;

class ResetController extends Controller
{
    public function __construct(protected ResetService $resetService) {}

    /**
     * GET /utilities/reset
     * Show the reset utility page with a live snapshot of current data counts.
     */
    public function index()
    {
        $snapshot = $this->resetService->snapshot();
        return view('utilities.reset', compact('snapshot'));
    }

    /**
     * POST /utilities/reset/api-data
     * Clears all API-produced runtime data (history, ledger, floats, game days).
     * Preserves all configuration.
     */
    public function apiDataReset(Request $request)
    {
        $request->validate([
            'confirmation' => ['required', 'in:RESET API DATA'],
        ], [
            'confirmation.in' => 'You must type exactly: RESET API DATA',
        ]);

        try {
            $counts = $this->resetService->apiDataReset();

            $total = array_sum($counts);

            return back()->with('success', "API Data Reset complete. {$total} records cleared across " . count($counts) . " tables.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Reset failed: ' . $e->getMessage());
        }
    }

    /**
     * POST /utilities/reset/full-db
     * Wipes the entire database and re-seeds to factory defaults.
     */
    public function fullDbReset(Request $request)
    {
        $request->validate([
            'confirmation' => ['required', 'in:FULL DB RESET'],
        ], [
            'confirmation.in' => 'You must type exactly: FULL DB RESET',
        ]);

        try {
            $counts = $this->resetService->fullDbReset();

            $total = array_sum($counts);

            // Log the user out since their session/user record was wiped and re-seeded
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', "Full DB Reset complete. {$total} records wiped. Database re-seeded to factory defaults. Please log in again.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Reset failed: ' . $e->getMessage());
        }
    }
}
