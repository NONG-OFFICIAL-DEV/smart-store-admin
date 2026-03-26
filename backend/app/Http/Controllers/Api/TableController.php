<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TableController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = min((int) $request->get('per_page', 10), 100);
        $query = Table::query();
        if ($search = $request->get('search')) {
            $query->where('name', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%");
        }
        $items = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tables retrieved successfully.',
            'data'    => $items,
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return Table::store($request);
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
        return Table::store($request, $id);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $record = Table::find($id);

        if (!$record) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $record->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product removed'
        ]);
    }

    public function qrCode(string $id)
    {
        $table = Table::with('branch')->findOrFail($id);

        if (!$table->qr_image_path) {
            $table->generateQrCode();
            $table->refresh();
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'table_id'     => $table->id,
                'table_number' => $table->table_number,
                'branch_name'  => $table->branch?->name,
                'branch_slug'  => $table->branch?->slug,
                'url'          => $table->qr_code,       // menu URL for scanning
                'qr_image_url' => $table->qr_image_url,  // ← accessor, returns full URL
            ]
        ]);
    }
    public function downloadQrCode(string $id)
    {
        $table = Table::with('branch')->findOrFail($id);

        // Regenerate if missing
        if (!$table->qr_image_path) {
            $table->generateQrCode();
            $table->refresh();
        }

        $path = 'qrcodes/tables/' . $table->id . '.svg';

        if (!Storage::disk('public')->exists($path)) {
            $table->generateQrCode();
        }

        $filename = 'QR-Table-' . $table->table_number . '-' . $table->branch->name . '.svg';
        $filename = preg_replace('/[^A-Za-z0-9\-_.]/', '-', $filename);

        return response()->streamDownload(function () use ($path) {
            echo Storage::disk('public')->get($path);
        }, $filename, [
            'Content-Type'        => 'image/svg',
            'Content-Disposition' => 'attachment',
        ]);
    }
    public function regenerateQrCode(string $id)
    {
        $table = Table::with('branch')->findOrFail($id);
        $table->generateQrCode();
        $table->refresh();

        return response()->json([
            'success' => true,
            'data'    => [
                'table_id'     => $table->id,
                'table_number' => $table->table_number,
                'url'          => $table->qr_code,
                'qr_image_url' => $table->qr_image_url, // ← accessor
            ]
        ]);
    }
}
