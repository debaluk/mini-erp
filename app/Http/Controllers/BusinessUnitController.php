<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use Illuminate\Http\Request;
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

    private function accounts(int $entityId)
    {
        return DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->whereIn('type', ['cogs', 'expense'])
            ->orderBy('code')
            ->get();
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

        $accounts = $this->accounts($entityId);

        return view('settings.business-units', compact('units', 'editUnit', 'accounts'));
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
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic'])],
            'hpp_account_id' => [
                'nullable', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('entity_id', $entityId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        BusinessUnit::create([
            'entity_id' => $entityId,
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'business_type' => $data['business_type'],
            'hpp_method' => $data['hpp_method'],
            'hpp_account_id' => $data['hpp_account_id'] ?? null,
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
            'hpp_method' => ['required', Rule::in(['perpetual', 'periodic'])],
            'hpp_account_id' => [
                'nullable', 'integer',
                Rule::exists('chart_of_accounts', 'id')->where(fn ($q) => $q->where('entity_id', $entityId)),
            ],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $unit->update([
            'code' => strtoupper(trim($data['code'])),
            'name' => trim($data['name']),
            'business_type' => $data['business_type'],
            'hpp_method' => $data['hpp_method'],
            'hpp_account_id' => $data['hpp_account_id'] ?? null,
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
