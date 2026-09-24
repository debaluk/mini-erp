<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use Illuminate\Http\Request;
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

        $editUnit = $request->filled('edit')
            ? BusinessUnit::where('entity_id', $entityId)->findOrFail((int) $request->input('edit'))
            : null;

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
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('business_units', 'code')->where(fn ($q) => $q->where('entity_id', $entityId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', Rule::in(['retail', 'production', 'service'])],
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic', 'direct_cost'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        BusinessUnit::create([
            'entity_id' => $entityId,
            'code' => strtoupper(trim($data['code'])),
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
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('business_units', 'code')
                    ->ignore($unit->id)
                    ->where(fn ($q) => $q->where('entity_id', $entityId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'business_type' => ['required', Rule::in(['retail', 'production', 'service'])],
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic', 'direct_cost'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update([
            'code' => strtoupper(trim($data['code'])),
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
        $unit->delete();

        return back()->with('success', 'Unit bisnis berhasil dihapus.');
    }
}
