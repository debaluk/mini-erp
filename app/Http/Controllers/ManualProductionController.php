<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManualProductionController extends Controller
{
    private function entityId(): int
    {
        return (int) (auth()->user()?->entity_id ?? 0);
    }

    public function options()
    {
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');
        $boms = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'b.business_unit_id')
            ->where('b.entity_id', $entityId)->where('b.is_active', 1)
            ->where('bu.business_type', 'production')
            ->orderBy('p.name')
            ->get(['b.id', 'b.business_unit_id', 'b.code', 'b.output_qty', 'p.name as product_name']);
        $warehouses = DB::table('warehouses as w')
            ->join('business_units as bu', 'bu.id', '=', 'w.business_unit_id')
            ->where('w.entity_id', $entityId)->where('w.is_active', 1)
            ->where('bu.business_type', 'production')->orderBy('w.name')
            ->get(['w.id', 'w.business_unit_id', 'w.name']);
        $workers = DB::table('workers')->where('entity_id', $entityId)->where('is_active', 1)->orderBy('name')->get(['id', 'code', 'name']);
        return response()->json(['boms' => $boms, 'warehouses' => $warehouses, 'workers' => $workers]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'production_date' => ['required', 'date'],
            'bom_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'good_qty' => ['required', 'numeric', 'gt:0'],
            'reject_qty' => ['nullable', 'numeric', 'gte:0'],
            'worker_id' => ['required', 'integer'],
            'pay_type' => ['required', 'in:borongan,satuan'],
            'unit_rate' => ['required', 'numeric', 'gte:0'],
        ]);
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');
        $goodQty = round((float) $data['good_qty'], 3);
        $rejectQty = round((float) ($data['reject_qty'] ?? 0), 3);
        $worker = DB::table('workers')->where('entity_id', $entityId)->where('is_active', 1)->where('id', $data['worker_id'])->first();
        abort_unless($worker, 422, 'Pekerja aktif tidak ditemukan pada entitas ini.');
        $payType = $data['pay_type'];
        $unitRate = round((float) $data['unit_rate'], 2);
        $laborAmount = $payType === 'satuan' ? round(($goodQty + $rejectQty) * $unitRate, 2) : $unitRate;

        DB::transaction(function () use ($data, $entityId, $goodQty, $rejectQty, $worker, $payType, $unitRate, $laborAmount): void {
            $bom = DB::table('boms as b')
                ->join('business_units as bu', 'bu.id', '=', 'b.business_unit_id')
                ->where('b.entity_id', $entityId)->where('b.id', $data['bom_id'])
                ->where('b.is_active', 1)->where('bu.business_type', 'production')->first(['b.*']);
            abort_unless($bom, 422, 'BOM aktif produksi tidak ditemukan.');
            $warehouse = DB::table('warehouses')->where('entity_id', $entityId)->where('id', $data['warehouse_id'])
                ->where('business_unit_id', $bom->business_unit_id)->where('is_active', 1)->first();
            abort_unless($warehouse, 422, 'Gudang tidak sesuai unit bisnis BOM.');

            $materials = DB::table('bom_items as bi')->join('products as p', 'p.id', '=', 'bi.product_id')
                ->where('bi.bom_id', $bom->id)->get(['bi.product_id', 'bi.qty', 'p.base_unit_id']);
            abort_if($materials->isEmpty(), 422, 'BOM belum memiliki bahan baku.');
            $batch = ($goodQty + $rejectQty) / max((float) $bom->output_qty, 0.001);
            $materialCost = 0.0;
            $consumptions = [];
            foreach ($materials as $material) {
                $qty = round((float) $material->qty * $batch, 3);
                $stock = DB::table('warehouses_stocks')->where('entity_id', $entityId)->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $material->product_id)->lockForUpdate()->first();
                abort_unless($qty > 0, 422, 'Kuantitas bahan baku tidak valid.');
                // Produksi manual untuk uji stok/HPP tidak memvalidasi kecukupan stok.
                // Jika stok belum tersedia, gunakan biaya rata-rata nol dan jangan membuat stok negatif.
                $unitCost = (float) ($stock->avg_cost ?? 0);
                $cost = round($qty * $unitCost, 2);
                $materialCost += $cost;
                $consumptions[] = ['product_id' => $material->product_id, 'unit_id' => $material->base_unit_id, 'qty' => $qty,
                    'unit_cost' => $unitCost, 'cost' => $cost, 'stock_id' => $stock->id ?? null, 'stock_qty' => (float) ($stock->qty ?? 0)];
            }

            $productionId = DB::table('productions')->insertGetId([
                'entity_id' => $entityId, 'business_unit_id' => $bom->business_unit_id, 'warehouse_id' => $warehouse->id,
                'bom_id' => $bom->id, 'user_id' => auth()->id(), 'production_no' => 'MAN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'production_date' => $data['production_date'].' 00:00:00', 'qty' => $batch, 'total_cost' => $materialCost,
                'good_output_qty' => $goodQty, 'reject_qty' => $rejectQty, 'status' => 'posted', 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('production_costs')->insert([
                'production_id' => $productionId, 'cost_group' => 'U',
                'description' => 'Upah '.$worker->name.' ('.($payType === 'satuan' ? 'Satuan' : 'Borongan').', tarif '.number_format($unitRate, 2, ',', '.').')',
                'amount' => $laborAmount, 'source' => 'manual_production',
                'reference_type' => 'worker', 'reference_id' => $worker->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            foreach ($consumptions as $item) {
                if ($item['stock_id'] !== null) {
                    DB::table('warehouses_stocks')->where('id', $item['stock_id'])->update(['qty' => $item['stock_qty'] - $item['qty'], 'updated_at' => now()]);
                }
                DB::table('production_material_usages')->insert(['production_id' => $productionId, 'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouse->id, 'qty' => $item['qty'], 'unit_cost' => $item['unit_cost'], 'total_cost' => $item['cost'],
                    'source' => 'manual_bom', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('stock_movements')->insert(['entity_id' => $entityId, 'business_unit_id' => $bom->business_unit_id,
                    'warehouse_id' => $warehouse->id, 'product_id' => $item['product_id'], 'unit_id' => $item['unit_id'],
                    'transaction_qty' => $item['qty'], 'conversion_factor' => 1, 'movement_type' => 'production_out', 'qty' => -$item['qty'],
                    'unit_cost' => $item['unit_cost'], 'reference_type' => 'manual_production', 'reference_id' => $productionId,
                    'occurred_at' => $data['production_date'].' 00:00:00', 'created_by' => auth()->id() ?? 1, 'created_at' => now(), 'updated_at' => now()]);
            }

            $finished = DB::table('products')->where('entity_id', $entityId)->where('id', $bom->product_id)->first();
            abort_unless($finished, 422, 'Produk hasil BOM tidak valid.');
            $outputCost = round($materialCost / max($goodQty, 0.001), 6);
            $stock = DB::table('warehouses_stocks')->where('entity_id', $entityId)->where('warehouse_id', $warehouse->id)
                ->where('product_id', $finished->id)->lockForUpdate()->first();
            if ($stock) {
                $newQty = (float) $stock->qty + $goodQty;
                $newAvg = $newQty > 0 ? (((float) $stock->qty * (float) $stock->avg_cost) + ($goodQty * $outputCost)) / $newQty : $outputCost;
                DB::table('warehouses_stocks')->where('id', $stock->id)->update(['qty' => $newQty, 'avg_cost' => round($newAvg, 9), 'updated_at' => now()]);
            } else {
                DB::table('warehouses_stocks')->insert(['entity_id' => $entityId, 'warehouse_id' => $warehouse->id, 'product_id' => $finished->id,
                    'qty' => $goodQty, 'avg_cost' => $outputCost, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('production_outputs')->insert(['production_id' => $productionId, 'product_id' => $finished->id, 'warehouse_id' => $warehouse->id,
                'qty' => $goodQty, 'unit_cost' => $outputCost, 'total_cost' => round($goodQty * $outputCost, 2), 'output_type' => 'good',
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('stock_movements')->insert(['entity_id' => $entityId, 'business_unit_id' => $bom->business_unit_id, 'warehouse_id' => $warehouse->id,
                'product_id' => $finished->id, 'unit_id' => $finished->base_unit_id, 'transaction_qty' => $goodQty, 'conversion_factor' => 1,
                'movement_type' => 'production_in', 'qty' => $goodQty, 'unit_cost' => $outputCost, 'reference_type' => 'manual_production',
                'reference_id' => $productionId, 'occurred_at' => $data['production_date'].' 00:00:00', 'created_by' => auth()->id() ?? 1,
                'created_at' => now(), 'updated_at' => now()]);
            if ($rejectQty > 0) DB::table('production_rejects')->insert(['production_id' => $productionId, 'product_id' => $finished->id,
                'qty' => $rejectQty, 'reject_type' => 'scrap', 'description' => 'Reject produksi manual', 'recoverable_value' => 0,
                'created_at' => now(), 'updated_at' => now()]);
        });
        return redirect()->route('produksi.work-order.hasil-report')->with('success', 'Produksi manual diposting tanpa jurnal. Data pekerja dan upah tersimpan; HPP barang jadi tetap berdasarkan bahan baku.');
    }

    public function destroy(int $id)
    {
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        DB::transaction(function () use ($id, $entityId): void {
            $production = DB::table('productions')->where('id', $id)
                ->where('entity_id', $entityId)->whereNull('production_work_order_id')
                ->where('production_no', 'like', 'MAN-%')->where('status', 'posted')
                ->lockForUpdate()->first();
            abort_unless($production, 404, 'Produksi manual tidak ditemukan.');

            $costIds = DB::table('production_costs')->where('production_id', $id)->pluck('id');
            $voucherIds = DB::table('voucher_bayar_items')->where('production_id', $id)->pluck('voucher_bayar_id');
            if ($costIds->isNotEmpty()) {
                $voucherIds = $voucherIds->merge(DB::table('voucher_bayar_items')->whereIn('production_cost_id', $costIds)->pluck('voucher_bayar_id'))->unique()->values();
            }
            // Voucher yang belum dibayar tidak mengunci penghapusan produksi manual.
            $hasPostedPayment = $voucherIds->isNotEmpty() && DB::table('voucher_bayar_payments as vbp')
                ->join('voucher_bayar as vb', 'vb.id', '=', 'vbp.voucher_bayar_id')
                ->whereIn('vbp.voucher_bayar_id', $voucherIds)
                ->where('vb.entity_id', $entityId)
                ->where('vb.category', 'UPAH_PRODUKSI')
                ->whereIn('vb.status', ['DIBAYAR_SEBAGIAN', 'LUNAS'])
                ->where('vb.paid_amount', '>', 0)
                ->whereNotNull('vbp.journal_id')
                ->exists();
            abort_if($hasPostedPayment, 422, 'Produksi manual tidak dapat dihapus karena pembayaran upahnya sudah diposting.');

            foreach (DB::table('production_outputs')->where('production_id', $id)->get() as $output) {
                $stock = DB::table('warehouses_stocks')->where('entity_id', $entityId)
                    ->where('warehouse_id', $output->warehouse_id)->where('product_id', $output->product_id)
                    ->lockForUpdate()->first();
                if ($stock) {
                    $oldQty = (float) $stock->qty;
                    $newQty = $oldQty - (float) $output->qty;
                    $newAvg = $newQty > 0
                        ? max(0, (($oldQty * (float) $stock->avg_cost) - ((float) $output->qty * (float) $output->unit_cost)) / $newQty)
                        : 0;
                    DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                        'qty' => $newQty, 'avg_cost' => round($newAvg, 9), 'updated_at' => now(),
                    ]);
                }
            }

            foreach (DB::table('production_material_usages')->where('production_id', $id)->get() as $usage) {
                $stock = DB::table('warehouses_stocks')->where('entity_id', $entityId)
                    ->where('warehouse_id', $usage->warehouse_id)->where('product_id', $usage->product_id)
                    ->lockForUpdate()->first();
                if ($stock) {
                    DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                        'qty' => (float) $stock->qty + (float) $usage->qty, 'updated_at' => now(),
                    ]);
                } else {
                    DB::table('warehouses_stocks')->insert([
                        'entity_id' => $entityId, 'warehouse_id' => $usage->warehouse_id, 'product_id' => $usage->product_id,
                        'qty' => (float) $usage->qty, 'avg_cost' => (float) $usage->unit_cost,
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            DB::table('stock_movements')->where('reference_type', 'manual_production')->where('reference_id', $id)->delete();
            DB::table('production_outputs')->where('production_id', $id)->delete();
            DB::table('production_material_usages')->where('production_id', $id)->delete();
            DB::table('production_rejects')->where('production_id', $id)->delete();
            DB::table('production_costs')->where('production_id', $id)->delete();
            DB::table('productions')->where('id', $id)->delete();
        });

        return redirect()->route('produksi.work-order.hasil-report')
            ->with('success', 'Produksi manual dihapus dan mutasi stoknya dibatalkan.');
    }

}
