<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DailySalesSummary;
use Illuminate\Http\Request;

class DailySalesSummaryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 15), 100);
        $query = DailySalesSummary::query();
        if ($search = $request->get('search')) {
            $query->where('date', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'date'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Daily sales summaries retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
