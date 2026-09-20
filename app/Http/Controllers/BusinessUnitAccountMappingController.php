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

    private function keys(string $type): array
    {
        return match ($type) {
            'retail' => ['hpp_merchandise'],
            'production' => ['hpp_finished_goods', 'direct_material', 'direct_labor', 'direct_freight'],
            'service' => ['direct_labor', 'direct_material', 'direct_other'],
            default => [],
        };
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);
        $units = BusinessUnit::where('entity_id', $entityId)->orderBy('code')->get();
        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)->where('is_active', true)->where('is_postable', true)
            ->orderBy('code')->get();
        $mappings = BusinessUnitAccountMapping::where('entity_id', $entityId)
            ->get()->keyBy(fn ($row) => $row->business_unit_id . ':' . $row->mapping_key);

        return view('settings.account-mapping', compact('units', 'accounts', 'mappings'));
    }

    public function save(Request $request, int $unitId)
    {
        $entityId = $this->entityId($request);
        $unit = BusinessUnit::where('entity_id', $entityId)->findOrFail($unitId);
        $keys = $this->keys($unit->business_type);

        $data = $request->validate(['accounts' => ['required', 'array'], 'accounts.*' => ['nullable', 'integer']]);

        $ids = array_values(array_filter($data['accounts'], fn ($v) => $v !== null && $v !== ''));
        if ($ids) {
            $valid = DB::table('chart_of_accounts')->where('entity_id', $entityId)
                ->where('is_active', true)->where('is_postable', true)->whereIn('id', $ids)->pluck('id')->all();
            if (count($valid) !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['accounts' => 'Akun tidak valid untuk entitas ini.']);
            }
        }

        foreach ($keys as $key) {
            $accountId = $data['accounts'][$key] ?? null;
            if (!$accountId) throw ValidationException::withMessages(["accounts.$key" => 'Mapping akun wajib diisi.']);
            BusinessUnitAccountMapping::updateOrCreate(
                ['entity_id' => $entityId, 'business_unit_id' => $unit->id, 'mapping_key' => $key],
                ['account_id' => $accountId]
            );
        }

        return back()->with('success', "Mapping akun {$unit->code} berhasil disimpan.");
    }
}