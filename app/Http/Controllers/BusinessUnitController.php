<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessUnitController extends Controller
{
    private function entityId(Request $request): int
    {
        $entityId = (int) $request->user()->entity_id;
        abort_unless($entityId, 403);

        return $entityId;
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);

        $units = BusinessUnit::where('entity_id', $entityId)
            ->orderBy('code')
            ->get();

        $editUnit = null;

        if ($request->filled('edit')) {
            try {
                $editId = (int) Crypt::decryptString($request->input('edit'));
            } catch (DecryptException $e) {
                abort(404);
            }

            $editUnit = BusinessUnit::where('entity_id', $entityId)->findOrFail($editId);
        }

        return view('master.unit-bisnis.index', compact('units', 'editUnit'));
    }

    public function edit(Request $request, int $id)
    {
        $unit = BusinessUnit::where('entity_id', $this->entityId($request))->findOrFail($id);

        return response()->json([
            'id' => $unit->id,
            'code' => $unit->code,
            'name' => $unit->name,
            'business_type' => $unit->business_type,
            'hpp_method' => $unit->hpp_method,
            'is_active' => (bool) $unit->is_active,
        ]);
    }

    public function store(Request $request)
    {
        $entityId = $this->entityId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', Rule::in(['retail', 'production', 'service'])],
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic', 'direct_cost'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        do {
            $code = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        } while (DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('code', $code)
            ->exists());

        BusinessUnit::create([
            'entity_id' => $entityId,
            'code' => $code,
            'name' => trim($data['name']),
            'business_type' => $data['business_type'],
            'hpp_method' => $data['hpp_method'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Unit bisnis berhasil ditambahkan.');
    }

    public function update(Request $request, int $id)
    {
        $entityId = $this->entityId($request);

        $unit = BusinessUnit::where('entity_id', $entityId)->findOrFail($id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', Rule::in(['retail', 'production', 'service'])],
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic', 'direct_cost'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update([
            'name' => trim($data['name']),
            'business_type' => $data['business_type'],
            'hpp_method' => $data['hpp_method'],
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Unit bisnis berhasil diperbarui.');
    }

    public function destroy(Request $request, int $id)
    {
        $entityId = $this->entityId($request);
        $unit = BusinessUnit::where('entity_id', $entityId)->findOrFail($id);

        // Periksa relasi yang memakai business_unit_id, termasuk tabel tanpa foreign key.
        $references = DB::select(
            "SELECT TABLE_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND COLUMN_NAME = 'business_unit_id'
               AND TABLE_NAME <> 'business_units'"
        );

        foreach ($references as $reference) {
            $table = $reference->TABLE_NAME;

            if (DB::table($table)->where('business_unit_id', $id)->exists()) {
                return back()->withErrors([
                    'delete' => 'Unit bisnis tidak dapat dihapus karena sudah digunakan oleh gudang, mapping, master, atau transaksi. Nonaktifkan unit bisnis jika tidak digunakan lagi.',
                ]);
            }
        }

        $foreignKeys = DB::select(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
               AND REFERENCED_TABLE_NAME = 'business_units'
               AND REFERENCED_COLUMN_NAME = 'id'"
        );

        foreach ($foreignKeys as $reference) {
            $table = $reference->TABLE_NAME;
            $column = $reference->COLUMN_NAME;

            if ($table !== 'business_units' && DB::table($table)->where($column, $id)->exists()) {
                return back()->withErrors([
                    'delete' => 'Unit bisnis tidak dapat dihapus karena masih menjadi acuan data lain. Nonaktifkan unit bisnis jika tidak digunakan lagi.',
                ]);
            }
        }

        $unit->delete();

        return back()->with('success', 'Unit bisnis berhasil dihapus.');
    }
}
