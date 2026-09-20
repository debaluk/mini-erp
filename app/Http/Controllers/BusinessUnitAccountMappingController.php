<?php

namespace App\Http\Controllers;

use App\Models\BusinessUnit;
use App\Models\BusinessUnitAccountMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessUnitAccountMappingController extends Controller
{
    private function entityId(Request $request): int
    {
        $id = (int) $request->user()->entity_id;
        abort_unless($id, 403);
        return $id;
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);

        $units = BusinessUnit::where('entity_id', $entityId)->orderBy('code')->get();

        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $mappings = BusinessUnitAccountMapping::where('entity_id', $entityId)
            ->where('mapping_key', 'hpp')
            ->get()
            ->keyBy('business_unit_id');

        return view('settings.account-mapping', compact('units', 'accounts', 'mappings'));
    }

    public function save(Request $request)
    {
        $entityId = $this->entityId($request);

        $data = $request->validate([
            'accounts' => ['required', 'array'],
            'accounts.*' => ['required', 'integer'],
        ]);

        $units = BusinessUnit::where('entity_id', $entityId)
            ->whereIn('id', array_keys($data['accounts']))
            ->get()
            ->keyBy('id');

        if ($units->count() !== count($data['accounts'])) {
            throw ValidationException::withMessages([
                'accounts' => 'Unit Bisnis tidak valid.',
            ]);
        }

        $ids = array_values($data['accounts']);
        $validIds = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($validIds) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'accounts' => 'Akun tidak valid untuk entitas ini.',
            ]);
        }

        DB::transaction(function () use ($data, $units, $entityId) {
            foreach ($data['accounts'] as $unitId => $accountId) {
                BusinessUnitAccountMapping::updateOrCreate(
                    [
                        'entity_id' => $entityId,
                        'business_unit_id' => $units[$unitId]->id,
                        'mapping_key' => 'hpp',
                    ],
                    ['account_id' => $accountId]
                );
            }
        });

        return back()->with('success', 'Mapping HPP berhasil disimpan.');
    }
}