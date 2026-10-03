<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductionWorkOrderExport;
use App\Helpers\FormatHelper;

class ProductionWorkOrderController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function index(Request $request)
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

        $businessUnits = DB::table('business_units')
            ->where('entity_id', $entityId)
            ->where('business_type', 'production')
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $workers = DB::table('workers')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $query = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'wo.business_unit_id')
            ->where('wo.entity_id', $entityId)
            ->select(
                'wo.*',
                'b.code as bom_code',
                'b.name as bom_name',
                'p.name as product_name',
                'w.name as warehouse_name',
                'bu.name as business_unit_name'
            )
            ->when($request->filled('status'), fn ($q) => $q->where('wo.status', $request->string('status')))
            ->whereDate('wo.wo_date', '>=', $dateFrom)
            ->whereDate('wo.wo_date', '<=', $dateTo)
            ->orderByDesc('wo.wo_date')
            ->orderByDesc('wo.id');

        $rows = $query->get();

        $workerCounts = DB::table('production_work_order_workers')
            ->select('production_work_order_id', DB::raw('COUNT(*) as total_workers'))
            ->whereIn('production_work_order_id', $rows->pluck('id'))
            ->groupBy('production_work_order_id')
            ->pluck('total_workers', 'production_work_order_id');

        return view('inventori.produksi.work-order.index', compact(
            'boms',
            'warehouses',
            'businessUnits',
            'workers',
            'rows',
            'workerCounts',
            'dateFrom',
            'dateTo'
        ));
    }

    public function create(Request $request)
    {
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $boms = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'b.business_unit_id')
            ->where('b.entity_id', $entityId)->where('b.is_active', 1)->where('bu.business_type', 'production')
            ->select('b.id','b.code','b.name','b.output_qty','b.business_unit_id','p.name as product_name')->orderBy('b.code')->get();
        $warehouses = DB::table('warehouses as w')->join('business_units as bu','bu.id','=','w.business_unit_id')
            ->where('w.entity_id',$entityId)->where('w.is_active',1)->where('bu.business_type','production')
            ->select('w.id','w.code','w.name','w.business_unit_id')->orderBy('w.name')->get();
        $businessUnits = DB::table('business_units')->where('entity_id',$entityId)->where('business_type','production')->orderBy('name')->get(['id','code','name']);
        $workers = DB::table('workers')->where('entity_id',$entityId)->where('is_active',1)->orderBy('name')->get(['id','code','name']);

        return view('inventori.produksi.work-order.create', compact('boms','warehouses','businessUnits','workers'));
    }

    public function bomInfo(Request $request, int $bomId)
    {
        $entityId = $this->entityId();
        $warehouseId = (int) $request->integer('warehouse_id');
        $batchQty = max((float) $request->input('batch_qty', 1), 0);

        $bom = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'p.base_unit_id')
            ->where('b.entity_id', $entityId)
            ->where('b.id', $bomId)
            ->where('b.is_active', 1)
            ->first([
                'b.id', 'b.code', 'b.name', 'b.output_qty',
                'p.name as product_name', 'p.sku as product_sku',
                'u.code as output_unit_code', 'u.name as output_unit_name',
            ]);

        abort_unless($bom, 404);

        $items = DB::table('bom_items as bi')
            ->join('products as p', 'p.id', '=', 'bi.product_id')
            ->join('units as u', 'u.id', '=', 'bi.unit_id')
            ->where('bi.bom_id', $bomId)
            ->orderBy('bi.id')
            ->get(['bi.product_id', 'bi.unit_id', 'bi.qty', 'p.sku', 'p.name as product_name', 'p.base_unit_id', 'u.code as unit_code', 'u.name as unit_name']);

        $materialCost = 0.0;
        $materials = $items->map(function ($item) use ($entityId, $warehouseId, $batchQty, &$materialCost) {
            $factor = 1.0;
            if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                $factor = (float) DB::table('product_unit_conversions')
                    ->where('product_id', $item->product_id)
                    ->where('unit_id', $item->unit_id)
                    ->where('is_active', 1)
                    ->value('conversion_factor');
            }

            $stock = $warehouseId
                ? DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('product_id', $item->product_id)
                    ->first(['avg_cost'])
                : null;

            if ($stock) {
                $unitCost = (float) $stock->avg_cost;
            } else {
                $avgCost = DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)
                    ->where('product_id', $item->product_id)
                    ->where('qty', '>', 0)
                    ->avg('avg_cost');

                $unitCost = (float) ($avgCost ?? 0);
            }
            $baseQty = round((float) $item->qty * $factor * $batchQty, 3);
            $lineCost = round($baseQty * $unitCost, 2);
            $materialCost += $lineCost;

            return [
                'sku' => $item->sku,
                'name' => $item->product_name,
                'qty' => (float) $item->qty,
                'unit' => $item->unit_code ?: $item->unit_name,
                'base_qty' => $baseQty,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
            ];
        })->values();

        return response()->json([
            'bom' => [
                'code' => $bom->code,
                'name' => $bom->name,
                'product_name' => $bom->product_name,
                'product_sku' => $bom->product_sku,
                'output_qty' => (float) $bom->output_qty,
                'output_unit' => $bom->output_unit_code ?: $bom->output_unit_name,
                'target_output_qty' => round((float) $bom->output_qty * $batchQty, 3),
            ],
            'materials' => $materials,
            'material_cost' => round($materialCost, 2),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge([
            'worker_amount' => collect($request->input('worker_amount', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
            'cost_amount' => collect($request->input('cost_amount', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
        ]);

        $data = $request->validate([
            'wo_date' => ['required', 'date'],
            'business_unit_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'bom_id' => ['required', 'integer'],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'worker_id' => ['required', 'array', 'min:1'],
            'worker_id.*' => ['required', 'integer', 'distinct'],
            'worker_amount' => ['required', 'array', 'min:1'],
            'worker_amount.*' => ['required', 'numeric', 'gt:0'],
            'cost_group' => ['nullable', 'array'],
            'cost_group.*' => ['nullable', 'in:A,S,O'],
            'cost_description' => ['nullable', 'array'],
            'cost_description.*' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['nullable', 'array'],
            'cost_amount.*' => ['nullable', 'numeric'],
        ]);

        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        DB::transaction(function () use ($data, $entityId): void {
            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)
                ->where('is_active', 1)
                ->where('id', $data['warehouse_id'])
                ->first();

            abort_unless($warehouse, 422, 'Gudang produksi tidak valid.');
            abort_unless((int) $warehouse->business_unit_id === (int) $data['business_unit_id'], 422, 'Business Unit gudang tidak sesuai.');

            $bom = DB::table('boms')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $warehouse->business_unit_id)
                ->where('is_active', 1)
                ->where('id', $data['bom_id'])
                ->first();

            abort_unless($bom, 422, 'BOM produksi tidak valid.');

            $workers = DB::table('workers')
                ->where('entity_id', $entityId)
                ->where('is_active', 1)
                ->whereIn('id', $data['worker_id'])
                ->pluck('id');

            abort_unless($workers->count() === count($data['worker_id']), 422, 'Ada pekerja yang tidak valid.');

            $targetOutput = round((float) $bom->output_qty * (float) $data['batch_qty'], 3);
            $woNo = 'SPK-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3));

            $woId = DB::table('production_work_orders')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'bom_id' => $bom->id,
                'user_id' => auth()->id(),
                'wo_no' => $woNo,
                'wo_date' => $data['wo_date'],
                'batch_qty' => $data['batch_qty'],
                'target_output_qty' => $targetOutput,
                'status' => 'open',
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['worker_id'] as $i => $workerId) {
                DB::table('production_work_order_workers')->insert([
                    'production_work_order_id' => $woId,
                    'worker_id' => $workerId,
                    'role' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('production_work_order_costs')->insert([
                    'production_work_order_id' => $woId,
                    'worker_id' => $workerId,
                    'cost_group' => 'U',
                    'description' => 'Tenaga',
                    'amount' => round((float) $data['worker_amount'][$i], 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (($data['cost_group'] ?? []) as $i => $group) {
                $amount = round((float) (($data['cost_amount'] ?? [])[$i] ?? 0), 2);
                if ($amount <= 0) {
                    continue;
                }

                DB::table('production_work_order_costs')->insert([
                    'production_work_order_id' => $woId,
                    'worker_id' => null,
                    'cost_group' => $group,
                    'description' => trim((string) (($data['cost_description'] ?? [])[$i] ?? '')) ?: null,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('produksi.work-order')->with('success', 'SPK berhasil dibuat.');
    }

    public function edit(int $id)
    {
        $entityId = $this->entityId();

        $wo = DB::table('production_work_orders')
            ->where('entity_id', $entityId)
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        abort_unless($wo, 404, 'SPK Open tidak ditemukan.');

        $boms = DB::table('boms as b')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('business_units as bu', 'bu.id', '=', 'b.business_unit_id')
            ->where('b.entity_id', $entityId)->where('b.is_active', 1)->where('bu.business_type', 'production')
            ->select('b.id','b.code','b.name','b.output_qty','b.business_unit_id','p.name as product_name')
            ->orderBy('b.code')->get();

        $warehouses = DB::table('warehouses as w')
            ->join('business_units as bu','bu.id','=','w.business_unit_id')
            ->where('w.entity_id',$entityId)->where('w.is_active',1)->where('bu.business_type','production')
            ->select('w.id','w.code','w.name','w.business_unit_id')->orderBy('w.name')->get();

        $businessUnits = DB::table('business_units')->where('entity_id',$entityId)->where('business_type','production')->orderBy('name')->get(['id','code','name']);
        $workers = DB::table('workers')->where('entity_id',$entityId)->where('is_active',1)->orderBy('name')->get(['id','code','name']);

        $woWorkers = DB::table('production_work_order_workers')
            ->where('production_work_order_id', $id)->orderBy('id')->get();

        $woCosts = DB::table('production_work_order_costs')
            ->where('production_work_order_id', $id)->orderBy('id')->get();

        return view('inventori.produksi.work-order.edit', compact(
            'wo','boms','warehouses','businessUnits','workers','woWorkers','woCosts'
        ));
    }

    public function update(Request $request, int $id)
    {
        $request->merge([
            'worker_amount' => collect($request->input('worker_amount', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
            'cost_amount' => collect($request->input('cost_amount', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
        ]);

        $data = $request->validate([
            'wo_date' => ['required', 'date'],
            'business_unit_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'bom_id' => ['required', 'integer'],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'worker_id' => ['required', 'array', 'min:1'],
            'worker_id.*' => ['required', 'integer', 'distinct'],
            'worker_amount' => ['required', 'array', 'min:1'],
            'worker_amount.*' => ['required', 'numeric', 'gt:0'],
            'cost_group' => ['nullable', 'array'],
            'cost_group.*' => ['nullable', 'in:A,S,O'],
            'cost_description' => ['nullable', 'array'],
            'cost_description.*' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['nullable', 'array'],
            'cost_amount.*' => ['nullable', 'numeric'],
        ]);

        $entityId = $this->entityId();

        DB::transaction(function () use ($data, $entityId, $id): void {
            $wo = DB::table('production_work_orders')
                ->where('entity_id', $entityId)->where('id', $id)->where('status', 'open')->first();
            abort_unless($wo, 422, 'Hanya SPK Open yang dapat diedit.');

            $warehouse = DB::table('warehouses')
                ->where('entity_id', $entityId)->where('is_active', 1)->where('id', $data['warehouse_id'])->first();
            abort_unless($warehouse, 422, 'Gudang produksi tidak valid.');
            abort_unless((int) $warehouse->business_unit_id === (int) $data['business_unit_id'], 422, 'Business Unit gudang tidak sesuai.');

            $bom = DB::table('boms')
                ->where('entity_id', $entityId)->where('business_unit_id', $warehouse->business_unit_id)
                ->where('is_active', 1)->where('id', $data['bom_id'])->first();
            abort_unless($bom, 422, 'BOM produksi tidak valid.');

            $validWorkers = DB::table('workers')->where('entity_id', $entityId)->where('is_active', 1)
                ->whereIn('id', $data['worker_id'])->pluck('id');
            abort_unless($validWorkers->count() === count($data['worker_id']), 422, 'Ada pekerja yang tidak valid.');

            $targetOutput = round((float) $bom->output_qty * (float) $data['batch_qty'], 3);

            DB::table('production_work_orders')->where('id', $id)->update([
                'business_unit_id' => $warehouse->business_unit_id,
                'warehouse_id' => $warehouse->id,
                'bom_id' => $bom->id,
                'wo_date' => $data['wo_date'],
                'batch_qty' => $data['batch_qty'],
                'target_output_qty' => $targetOutput,
                'notes' => $data['notes'] ?? null,
                'updated_at' => now(),
            ]);

            DB::table('production_work_order_costs')->where('production_work_order_id', $id)->delete();
            DB::table('production_work_order_workers')->where('production_work_order_id', $id)->delete();

            foreach ($data['worker_id'] as $i => $workerId) {
                DB::table('production_work_order_workers')->insert([
                    'production_work_order_id' => $id,
                    'worker_id' => $workerId,
                    'role' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('production_work_order_costs')->insert([
                    'production_work_order_id' => $id,
                    'worker_id' => $workerId,
                    'cost_group' => 'U',
                    'description' => 'Tenaga',
                    'amount' => round((float) $data['worker_amount'][$i], 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (($data['cost_group'] ?? []) as $i => $group) {
                $amount = round((float) (($data['cost_amount'] ?? [])[$i] ?? 0), 2);
                if ($amount <= 0) continue;
                DB::table('production_work_order_costs')->insert([
                    'production_work_order_id' => $id,
                    'worker_id' => null,
                    'cost_group' => $group,
                    'description' => trim((string) (($data['cost_description'] ?? [])[$i] ?? '')) ?: null,
                    'amount' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('produksi.work-order')->with('success', 'WO berhasil diperbarui.');
    }

    public function startWork(int $id)
    {
        $entityId = $this->entityId();

        $wo = DB::table('production_work_orders')
            ->where('entity_id', $entityId)
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        abort_unless($wo, 422, 'Hanya SPK Open yang dapat dimulai.');

        DB::table('production_work_orders')
            ->where('id', $id)
            ->update([
                'status' => 'in_progress',
                'started_at' => now(),
                'updated_at' => now(),
            ]);

        return redirect()->route('produksi.work-order.show', $id)
            ->with('success', 'SPK berhasil dimulai. Rencana bahan tersedia untuk proses pengeluaran.');
    }

    public function show(int $id)
    {
        $entityId = $this->entityId();

        $wo = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $entityId)
            ->where('wo.id', $id)
            ->select(
                'wo.*',
                'b.code as bom_code',
                'b.name as bom_name',
                'p.name as product_name',
                'w.name as warehouse_name'
            )
            ->first();

        abort_unless($wo, 404);

        $workers = DB::table('production_work_order_workers as wow')
            ->join('workers as w', 'w.id', '=', 'wow.worker_id')
            ->where('wow.production_work_order_id', $id)
            ->orderBy('w.name')
            ->get(['w.code', 'w.name']);

        $items = DB::table('bom_items as bi')
            ->join('products as p', 'p.id', '=', 'bi.product_id')
            ->join('units as u', 'u.id', '=', 'bi.unit_id')
            ->where('bi.bom_id', $wo->bom_id)
            ->orderBy('bi.id')
            ->get([
                'bi.product_id',
                'bi.unit_id',
                'bi.qty',
                'p.sku',
                'p.name as product_name',
                'p.base_unit_id',
                'u.code as unit_code',
                'u.name as unit_name',
            ]);

        $materials = $items->map(function ($item) use ($wo) {
            $factor = 1.0;

            if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                $factor = (float) DB::table('product_unit_conversions')
                    ->where('product_id', $item->product_id)
                    ->where('unit_id', $item->unit_id)
                    ->where('is_active', 1)
                    ->value('conversion_factor');
            }

            return [
                'sku' => $item->sku,
                'name' => $item->product_name,
                'qty' => round((float) $item->qty * (float) $wo->batch_qty, 3),
                'unit' => $item->unit_code ?: $item->unit_name,
                'base_qty' => round((float) $item->qty * $factor * (float) $wo->batch_qty, 3),
            ];
        })->values();

        return view('inventori.produksi.work-order.show', compact('wo', 'workers', 'materials'));
    }

    public function destroy(int $id)
    {
        $entityId = $this->entityId();
        $wo = DB::table('production_work_orders')
            ->where('entity_id', $entityId)
            ->where('id', $id)
            ->where('status', 'open')
            ->first();

        abort_unless($wo, 422, 'Hanya SPK Open yang dapat dihapus.');

        DB::transaction(function () use ($id): void {
            DB::table('production_work_order_costs')->where('production_work_order_id', $id)->delete();
            DB::table('production_work_order_workers')->where('production_work_order_id', $id)->delete();
            DB::table('production_work_orders')->where('id', $id)->where('status', 'open')->delete();
        });

        return redirect()->route('produksi.work-order')->with('success', 'SPK Open berhasil dihapus.');
    }

    public function print(int $id)
    {
        $entityId = $this->entityId();

        $wo = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $entityId)
            ->where('wo.id', $id)
            ->select(
                'wo.*',
                'b.code as bom_code',
                'b.name as bom_name',
                'p.name as product_name',
                'p.code as product_code',
                'w.code as warehouse_code',
                'w.name as warehouse_name'
            )
            ->first();

        abort_unless($wo, 404);

        $workers = DB::table('production_work_order_workers as wow')
            ->join('workers as w', 'w.id', '=', 'wow.worker_id')
            ->where('wow.production_work_order_id', $id)
            ->orderBy('w.name')
            ->get(['w.code', 'w.name']);

        $entity = DB::table('entities')->where('id', $entityId)->first();

        return view('inventori.produksi.work-order.print', compact('wo', 'workers', 'entity'));
    }

    public function export(Request $request)
    {
        return Excel::download(
            new ProductionWorkOrderExport(
                $this->entityId(),
                $request->string('status')->toString(),
                $request->string('date_from')->toString(),
                $request->string('date_to')->toString()
            ),
            'daftar-spk-'.now()->format('Ymd-His').'.xlsx'
        );
    }
}
