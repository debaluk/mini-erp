<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionController extends Controller
{
    private const COST_GROUPS = [
        'B' => 'Bahan',
        'U' => 'Upah',
        'A' => 'Alat',
        'S' => 'Sewa',
        'O' => 'Overhead',
    ];

    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function index()
    {
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $boms = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'b.business_unit_id')
            ->where('b.entity_id', $entityId)
            ->where('b.is_active', 1)
            ->where('bu.business_type', 'production')
            ->select('b.id', 'b.code', 'b.name', 'b.output_qty', 'b.business_unit_id', 'p.name as product_name')
            ->orderBy('b.code')
            ->get();

        $warehouses = DB::table('warehouses as w')
            ->join('business_units as bu', 'bu.id', '=', 'w.business_unit_id')
            ->where('w.entity_id', $entityId)
            ->where('w.is_active', 1)
            ->where('bu.business_type', 'production')
            ->select('w.id', 'w.code', 'w.name', 'w.business_unit_id')
            ->orderBy('w.name')
            ->get();

        $rows = DB::table('productions as pr')
            ->join('boms as b', 'b.id', '=', 'pr.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin(DB::raw('(SELECT production_id, SUM(total_cost) total_material FROM production_material_usages GROUP BY production_id) mu'), 'mu.production_id', '=', 'pr.id')
            ->leftJoin(DB::raw('(SELECT production_id, SUM(amount) total_actual_cost FROM production_costs GROUP BY production_id) pc'), 'pc.production_id', '=', 'pr.id')
            ->where('pr.entity_id', $entityId)
            ->select(
                'pr.*',
                'b.code as bom_code',
                'p.name as product_name',
                DB::raw('COALESCE(mu.total_material,0) as material_cost'),
                DB::raw('COALESCE(pc.total_actual_cost,0) as additional_cost')
            )
            ->orderByDesc('pr.production_date')
            ->orderByDesc('pr.id')
            ->paginate(15);

        return view('inventori.produksi.index', [
            'boms' => $boms,
            'warehouses' => $warehouses,
            'rows' => $rows,
            'costGroups' => self::COST_GROUPS,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'bom_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'good_output_qty' => ['nullable', 'numeric', 'gte:0'],
            'reject_qty' => ['nullable', 'numeric', 'gte:0'],
            'cost_group' => ['nullable', 'array'],
            'cost_group.*' => ['nullable', 'in:B,U,A,S,O'],
            'cost_description' => ['nullable', 'array'],
            'cost_description.*' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['nullable', 'array'],
            'cost_amount.*' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        DB::transaction(function () use ($data, $entityId): void {
            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('is_active', 1)
                ->where('id', $data['warehouse_id'])
                ->first();
            abort_unless($warehouse, 422, 'Gudang tidak valid.');

            $bom = DB::table('boms')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $warehouse->business_unit_id)
                ->where('is_active', 1)
                ->where('id', $data['bom_id'])
                ->first();
            abort_unless($bom, 422, 'BOM tidak valid.');

            $items = DB::table('bom_items')->where('bom_id', $bom->id)->get();
            abort_if($items->isEmpty(), 422, 'BOM belum memiliki bahan.');

            $theoreticalOutput = round((float) $bom->output_qty * (float) $data['qty'], 3);
            $goodOutput = array_key_exists('good_output_qty', $data) && $data['good_output_qty'] !== null
                ? (float) $data['good_output_qty']
                : $theoreticalOutput;
            $rejectQty = (float) ($data['reject_qty'] ?? 0);

            abort_if($goodOutput <= 0, 422, 'Qty hasil bagus harus lebih dari 0.');
            abort_if(($goodOutput + $rejectQty) > ($theoreticalOutput + 0.000001), 422, 'Hasil bagus + reject melebihi output teoritis BOM.');

            $costGroups = $data['cost_group'] ?? [];
            $costDescriptions = $data['cost_description'] ?? [];
            $costAmounts = $data['cost_amount'] ?? [];
            $additionalCost = 0.0;

            foreach ($costGroups as $i => $group) {
                $amount = (float) ($costAmounts[$i] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                abort_if(!isset(self::COST_GROUPS[$group]), 422, 'Kelompok biaya produksi tidak valid.');
                $additionalCost += $amount;
            }

            $productionNo = 'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3));
            $productionId = DB::table('productions')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'bom_id' => $bom->id,
                'user_id' => auth()->id(),
                'production_no' => $productionNo,
                'production_date' => now(),
                'qty' => $data['qty'],
                'total_cost' => 0,
                'good_output_qty' => 0,
                'reject_qty' => 0,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $materialCost = 0.0;

            foreach ($items as $item) {
                $material = DB::table('products')
                    ->where('entity_id', $entityId)
                    ->where('id', $item->product_id)
                    ->first();
                abort_unless($material, 422, 'Bahan BOM tidak valid.');

                $need = round((float) $item->qty * (float) $data['qty'], 3);

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                abort_if(!$stock || (float) $stock->qty < $need, 422, 'Stok bahan baku tidak cukup.');

                $unitCost = (float) $stock->avg_cost;
                $lineCost = round($need * $unitCost, 2);
                $materialCost += $lineCost;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => (float) $stock->qty - $need,
                    'updated_at' => now(),
                ]);

                DB::table('production_material_usages')->insert([
                    'production_id' => $productionId,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $warehouse->id,
                    'qty' => $need,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineCost,
                    'source' => 'stock',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $warehouse->business_unit_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $material->base_unit_id,
                    'transaction_qty' => $need,
                    'conversion_factor' => 1,
                    'movement_type' => 'production_out',
                    'qty' => -$need,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'production',
                    'reference_id' => $productionId,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($costGroups as $i => $group) {
                $amount = round((float) ($costAmounts[$i] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                DB::table('production_costs')->insert([
                    'production_id' => $productionId,
                    'cost_group' => $group,
                    'description' => trim((string) ($costDescriptions[$i] ?? self::COST_GROUPS[$group])),
                    'amount' => $amount,
                    'source' => 'manual',
                    'reference_type' => null,
                    'reference_id' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $totalCost = round($materialCost + $additionalCost, 2);
            $unitCost = round($totalCost / $goodOutput, 4);

            $finished = DB::table('products')
                ->where('entity_id', $entityId)
                ->where('id', $bom->product_id)
                ->first();
            abort_unless($finished, 422, 'Produk hasil BOM tidak valid.');

            $stock = DB::table('warehouses_stocks')
                ->where('entity_id', $entityId)
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $bom->product_id)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $oldQty = (float) $stock->qty;
                $oldAvg = (float) $stock->avg_cost;
                $newQty = $oldQty + $goodOutput;
                $newAvg = $newQty > 0
                    ? (($oldQty * $oldAvg) + ($goodOutput * $unitCost)) / $newQty
                    : 0;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'avg_cost' => round($newAvg, 9),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entityId,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $bom->product_id,
                    'qty' => $goodOutput,
                    'avg_cost' => round($unitCost, 9),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('production_outputs')->insert([
                'production_id' => $productionId,
                'product_id' => $bom->product_id,
                'warehouse_id' => $warehouse->id,
                'qty' => $goodOutput,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'output_type' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($rejectQty > 0) {
                DB::table('production_rejects')->insert([
                    'production_id' => $productionId,
                    'product_id' => $bom->product_id,
                    'qty' => $rejectQty,
                    'reject_type' => 'scrap',
                    'description' => 'Reject produksi',
                    'recoverable_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_movements')->insert([
                'entity_id' => $entityId,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $bom->product_id,
                'unit_id' => $finished->base_unit_id,
                'transaction_qty' => $goodOutput,
                'conversion_factor' => 1,
                'movement_type' => 'production_in',
                'qty' => $goodOutput,
                'unit_cost' => $unitCost,
                'reference_type' => 'production',
                'reference_id' => $productionId,
                'occurred_at' => now(),
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('productions')->where('id', $productionId)->update([
                'total_cost' => $totalCost,
                'good_output_qty' => $goodOutput,
                'reject_qty' => $rejectQty,
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('produksi')->with('success', 'Produksi berhasil diposting dan HPP aktual tersimpan.');
    }
}
