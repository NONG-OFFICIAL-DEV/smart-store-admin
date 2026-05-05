<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BranchType;
use App\Models\BusinessType;
use Illuminate\Http\Request;

class BusinessTypeController extends Controller
{

    public function index()
    {
        $data = BusinessType::all();

        return response()->json([
            'status'  => true,
            'message' => 'Business types fetched successfully',
            'data'    => $data
        ], 200);
    }

    public function getBranchTypeByBusinessTypeId(string $id)
    {
        $data = BranchType::where('business_type_id', $id)
            ->where('is_active', true)
            ->orderBy('is_hq', 'desc')
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'status'  => true,
            'message' => 'Branch types fetched successfully',
            'data'    => $data
        ], 200);
    }
}
