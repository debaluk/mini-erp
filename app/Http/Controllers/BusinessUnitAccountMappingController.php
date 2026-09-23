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

    private function mappingKeys(string $businessType): array
    {
        return match ($businessType) {
            'retail' => [
                'cash',
                'bank',
                'receivable',
                'payable',
                'inventory',
                'sales_merchandise',
                'cogs_merchandise',
            ],
            'production' => [
                'cash',
                'bank',
                'receivable',
                'payable',
                'inventory',
                'sales_finished_goods',
                'cogs_finished_goods',
                'direct_material',
                'direct_labor',
                'direct_overhead',
            ],
            'service' => [
                'cash',
                'bank',
                'receivable',
                'payable',
                'sales_service',
                'direct_material',
                'direct_labor',
                'direct_overhead',
            ],
            default => [],
        };
    }

    private function mappingLabels(): array
    {
        return [
            'cash' => 'Kas',
            'bank' => 'Bank',
            'receivable' => 'Piutang',
            'payable' => 'Hutang',
            'inventory' => 'Persediaan',
            'sales_merchandise' => 'Penjualan Barang Dagangan',
            'sales_finished_goods' => 'Penjualan Hasil Produksi',
            'sales_service' => 'Pendapatan Jasa',
            'cogs_merchandise' => 'HPP Barang Dagangan',
            'cogs_finished_goods' => 'HPP Hasil Produksi',
            'direct_material' => 'Bahan Baku Langsung',
            'direct_labor' => 'Tenaga Kerja Langsung',
            'direct_overhead' => 'Overhead Langsung',
        ];
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId($request);

        $units = BusinessUnit::where('entity_id', $entityId)
            ->orderBy('code')
            ->get();

        $accounts = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->orderBy('code')
            ->get();

        $mappings = BusinessUnitAccountMapping::where('entity_id', $entityId)
            ->get()
            ->groupBy('business_unit_id')
            ->map(fn ($rows) => $rows->keyBy('mapping_key'));

        $mappingLabels = $this->mappingLabels();
        $mappingKeys = $units->mapWithKeys(fn ($unit) => [
            $unit->id => $this->mappingKeys($unit->business_type),
        ]);

        return view('settings.account-mapping', compact(
            'units',
            'accounts',
            'mappings',
            'mappingLabels',
            'mappingKeys'
        ));
    }

    public function save(Request $request)
    {
        $entityId = $this->entityId($request);

        $data = $request->validate([
            'accounts' => ['required', 'array'],
            'accounts.*' => ['required', 'array'],
            'accounts.*.*' => ['required', 'integer'],
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

        $allowedKeys = [];
        foreach ($units as $unit) {
            $allowedKeys[$unit->id] = $this->mappingKeys($unit->business_type);
        }

        $accountIds = [];
        foreach ($data['accounts'] as $unitId => $unitAccounts) {
            foreach ($unitAccounts as $mappingKey => $accountId) {
                if (!in_array($mappingKey, $allowedKeys[$unitId], true)) {
                    throw ValidationException::withMessages([
                        'accounts' => 'Mapping akun tidak valid untuk Unit Bisnis ' . $units[$unitId]->code . '.',
                    ]);
                }

                $accountIds[] = (int) $accountId;
            }
        }

        $validIds = DB::table('chart_of_accounts')
            ->where('entity_id', $entityId)
            ->where('is_active', true)
            ->where('is_postable', true)
            ->whereIn('id', array_unique($accountIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (count($validIds) !== count(array_unique($accountIds))) {
            throw ValidationException::withMessages([
                'accounts' => 'Akun tidak valid untuk entitas ini.',
            ]);
        }

        foreach ($allowedKeys as $unitId => $keys) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $data['accounts'][$unitId] ?? [])) {
                    throw ValidationException::withMessages([
                        'accounts' => 'Mapping ' . ($this->mappingLabels()[$key] ?? $key) . ' untuk Unit Bisnis ' . $units[$unitId]->code . ' wajib diisi.',
                    ]);
                }
            }
        }

        DB::transaction(function () use ($data, $units, $entityId) {
            foreach ($data['accounts'] as $unitId => $unitAccounts) {
                foreach ($unitAccounts as $mappingKey => $accountId) {
                    BusinessUnitAccountMapping::updateOrCreate(
                        [
                            'entity_id' => $entityId,
                            'business_unit_id' => $units[$unitId]->id,
                            'mapping_key' => $mappingKey,
                        ],
                        ['account_id' => $accountId]
                    );
                }
            }
        });

        return back()->with('success', 'Mapping akun Unit Bisnis berhasil disimpan.');
    }
}
