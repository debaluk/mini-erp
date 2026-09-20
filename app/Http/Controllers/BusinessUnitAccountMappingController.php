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

        // Hanya akun HPP utama yang boleh dipetakan.
        $hppAccount = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('code', '50001')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->first();

        $mappings = BusinessUnitAccountMapping::where('entity_id', $entityId)
            ->where('mapping_key', 'hpp')
            ->get()
            ->keyBy('business_unit_id');

        return view('settings.account-mapping', compact('units', 'hppAccount', 'mappings'));
    }

    public function save(Request $request, int $unitId)
    {
        $entityId = $this->entityId($request);
        $unit = BusinessUnit::where('entity_id', $entityId)->findOrFail($unitId);

        $hppAccount = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('code', '50001')
            ->where('is_active', true)
            ->where('is_postable', true)
            ->first();

        if (! $hppAccount) {
            throw ValidationException::withMessages([
                'accounts.hpp' => 'Akun 50001 — Harga Pokok Pendapatan belum tersedia atau belum aktif/postable.',
            ]);
        }

        BusinessUnitAccountMapping::updateOrCreate(
            [
                'entity_id' => $entityId,
                'business_unit_id' => $unit->id,
                'mapping_key' => 'hpp',
            ],
            ['account_id' => $hppAccount->id]
        );

        return back()->with('success', "Mapping HPP {$unit->code} berhasil disimpan.");
    }
}