<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $query = Category::query()->with('tenants');
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%");
        }
        $query->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_order', 'desc'));
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Categories retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    // CategoryController.php
    public function store(Request $request)
    {
        $category = Category::create($request->only([
            'parent_id',
            'name',
            'description',
            'image_url',
            'icon',
            'color',
            'sort_order',
            'is_active',
        ]));

        $category->tenants()->sync($request->input('tenant_ids', []));

        return response()->json($category->load('tenants'), 201);
    }

    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $category->update($request->only([
            'parent_id',
            'name',
            'description',
            'image_url',
            'icon',
            'color',
            'sort_order',
            'is_active',
        ]));

        $category->tenants()->sync($request->input('tenant_ids', []));

        return response()->json($category->load('tenants'), 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = Category::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category removed'
        ]);
    }
}
