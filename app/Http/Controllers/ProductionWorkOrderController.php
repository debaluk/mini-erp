<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductionWorkOrderExport;
use App\Helpers\FormatHelper;
use Carbon\Carbon;

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
                'p.base_unit_id as output_unit_id',
                'w.name as warehouse_name',
                'bu.name as business_unit_name'
            )
            ->when($request->filled('status'), fn ($q) => $q->where('wo.status', $request->string('status')))
            ->whereDate('wo.wo_date', '>=', $dateFrom)
            ->whereDate('wo.wo_date', '<=', $dateTo)
            ->orderByDesc('wo.wo_date')
            ->orderByDesc('wo.id');

        $rows = $query->get();

        $bomMaterials = DB::table('bom_items as bi')
            ->join('products as p', 'p.id', '=', 'bi.product_id')
            ->leftJoin('product_unit_conversions as puc', function ($join) {
                $join->on('puc.product_id', '=', 'bi.product_id')
                    ->on('puc.unit_id', '=', 'bi.unit_id')
                    ->where('puc.is_active', 1);
            })
            ->whereIn('bi.bom_id', $rows->pluck('bom_id')->unique())
            ->groupBy('bi.bom_id', 'bi.product_id')
            ->get([
                'bi.bom_id',
                'bi.product_id',
                DB::raw('SUM(bi.qty * CASE WHEN bi.unit_id = p.base_unit_id THEN 1 ELSE COALESCE(puc.conversion_factor, 1) END) as planned_base_qty'),
            ])
            ->groupBy('bom_id');

        $usedMaterials = DB::table('production_wo_material_usages as u')
            ->join('production_material_usage_items as ui', 'ui.production_material_usage_id', '=', 'u.id')
            ->whereIn('u.production_work_order_id', $rows->pluck('id'))
            ->where('u.status', 'approved')
            ->groupBy('u.production_work_order_id', 'ui.product_id')
            ->get([
                'u.production_work_order_id',
                'ui.product_id',
                DB::raw('SUM(ui.base_actual_qty) as used_base_qty'),
            ])
            ->groupBy('production_work_order_id');

        $productionResults = DB::table('productions')
            ->whereIn('production_work_order_id', $rows->pluck('id'))
            ->where('status', 'posted')
            ->groupBy('production_work_order_id')
            ->select('production_work_order_id', DB::raw('SUM(good_output_qty) as good_output_qty'))
            ->pluck('good_output_qty', 'production_work_order_id');

        foreach ($rows as $row) {
            $materials = $bomMaterials->get($row->bom_id, collect());
            $usedByProduct = $usedMaterials->get($row->id, collect())->keyBy('product_id');
            $percentages = $materials->map(function ($material) use ($usedByProduct, $row) {
                $planned = (float) $material->planned_base_qty * (float) $row->batch_qty;
                if ($planned <= 0) {
                    return null;
                }

                $used = (float) ($usedByProduct->get($material->product_id)->used_base_qty ?? 0);
                return min(100, ($used / $planned) * 100);
            })->filter(fn ($value) => $value !== null);

            $row->material_percent = $percentages->isNotEmpty()
                ? round($percentages->avg(), 1)
                : 0;
            $row->good_output_qty = (float) ($productionResults[$row->id] ?? 0);
        }

        $workerCounts = DB::table('production_work_order_workers')
            ->select('production_work_order_id', DB::raw('COUNT(*) as total_workers'))
            ->whereIn('production_work_order_id', $rows->pluck('id'))
            ->groupBy('production_work_order_id')
            ->pluck('total_workers', 'production_work_order_id');

        $woCosts = DB::table('production_work_order_costs')
            ->whereIn('production_work_order_id', $rows->pluck('id'))
            ->orderBy('id')
            ->get([
                'id',
                'production_work_order_id',
                'worker_id',
                'cost_group',
                'description',
                'amount',
                'pay_type',
                'unit_rate',
            ]);

        $woWorkers = DB::table('production_work_order_workers as wow')
            ->join('workers as w', 'w.id', '=', 'wow.worker_id')
            ->whereIn('wow.production_work_order_id', $rows->pluck('id'))
            ->orderBy('w.name')
            ->get([
                'wow.production_work_order_id',
                'wow.worker_id',
                'w.name as worker_name',
            ]);

        $resultDraftLines = DB::table('production_work_order_results as r')
            ->join('production_work_order_result_lines as l', 'l.production_work_order_result_id', '=', 'r.id')
            ->whereIn('r.production_work_order_id', $rows->pluck('id'))
            ->where('r.status', 'draft')
            ->orderByDesc('r.production_date')
            ->get([
                'r.production_work_order_id',
                'r.production_date',
                'l.worker_id',
                'l.good_qty',
                'l.reject_qty',
            ]);

        return view('inventori.produksi.work-order.index', compact(
            'boms',
            'warehouses',
            'businessUnits',
            'workers',
            'rows',
            'workerCounts',
            'woCosts',
            'woWorkers',
            'resultDraftLines',
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
            'worker_rate' => collect($request->input('worker_rate', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
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
            'worker_pay_type' => ['required', 'array', 'min:1'],
            'worker_pay_type.*' => ['required', 'in:borongan,satuan'],
            'worker_rate' => ['required', 'array', 'min:1'],
            'worker_rate.*' => ['required', 'numeric', 'gt:0'],
            'cost_group' => ['nullable', 'array'],
            'cost_group.*' => ['nullable', 'in:A,S,O'],
            'cost_description' => ['nullable', 'array'],
            'cost_description.*' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['nullable', 'array'],
            'cost_amount.*' => ['nullable', 'numeric'],
        ]);

        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $woId = null;
        DB::transaction(function () use ($data, $entityId, &$woId): void {
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
                    'amount' => round((float) $data['worker_rate'][$i] * (($data['worker_pay_type'][$i] ?? 'borongan') === 'satuan' ? $targetOutput : 1), 2),
                    'pay_type' => $data['worker_pay_type'][$i] ?? 'borongan',
                    'unit_rate' => round((float) $data['worker_rate'][$i], 2),
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'SPK berhasil dibuat.',
                'row' => $this->workOrderRow($entityId, $woId),
            ]);
        }

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
            'worker_rate' => collect($request->input('worker_rate', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
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
            'worker_pay_type' => ['required', 'array', 'min:1'],
            'worker_pay_type.*' => ['required', 'in:borongan,satuan'],
            'worker_rate' => ['required', 'array', 'min:1'],
            'worker_rate.*' => ['required', 'numeric', 'gt:0'],
            'cost_group' => ['nullable', 'array'],
            'cost_group.*' => ['nullable', 'in:A,S,O'],
            'cost_description' => ['nullable', 'array'],
            'cost_description.*' => ['nullable', 'string', 'max:255'],
            'cost_amount' => ['nullable', 'array'],
            'cost_amount.*' => ['nullable', 'numeric'],
        ]);

        $entityId = $this->entityId();

        $mappingKeys = [
            'direct_labor',
            'salary_payable',
            'inventory_finished_goods',
            'inventory_damage_loss',
        ];
        $productionMappings = DB::table('business_unit_account_mappings')
            ->where('entity_id', $entityId)
            ->where('business_unit_id', function ($query) use ($entityId, $id) {
                $query->from('production_work_orders')
                    ->select('business_unit_id')
                    ->where('entity_id', $entityId)
                    ->where('id', $id)
                    ->limit(1);
            })
            ->whereIn('mapping_key', $mappingKeys)
            ->pluck('account_id', 'mapping_key');

        if ($productionMappings->count() !== count($mappingKeys) || $productionMappings->contains(fn ($accountId) => empty($accountId))) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Mapping akun produksi belum lengkap. Silakan lengkapi mapping akun produksi terlebih dahulu.'], 422);
            }

            return redirect()->back()->with('error', 'Mapping akun produksi belum lengkap. Silakan lengkapi mapping akun produksi terlebih dahulu.');
        }

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
                    'amount' => round((float) $data['worker_rate'][$i] * (($data['worker_pay_type'][$i] ?? 'borongan') === 'satuan' ? $targetOutput : 1), 2),
                    'pay_type' => $data['worker_pay_type'][$i] ?? 'borongan',
                    'unit_rate' => round((float) $data['worker_rate'][$i], 2),
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

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'WO berhasil diperbarui.',
                'row' => $this->workOrderRow($entityId, $id),
            ]);
        }

        return redirect()->route('produksi.work-order')->with('success', 'WO berhasil diperbarui.');
    }

    public function startWork(Request $request, int $id)
    {
        $data = $request->validate([
            'started_at' => ['required', 'date'],
        ]);
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
                'started_at' => $data['started_at'].' 00:00:00',
                'updated_at' => now(),
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'SPK berhasil dimulai.',
            ]);
        }

        return redirect()->route('produksi.work-order.show', $id)
            ->with('success', 'SPK berhasil dimulai.');
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
            ->get(['wow.worker_id', 'w.code', 'w.name']);

        $woCosts = DB::table('production_work_order_costs as c')
            ->leftJoin('workers as w', 'w.id', '=', 'c.worker_id')
            ->where('c.production_work_order_id', $id)
            ->orderBy('c.id')
            ->get(['c.cost_group', 'c.description', 'c.amount', 'c.worker_id', 'w.name as worker_name']);

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

        $actualItems = DB::table('production_wo_material_usages as u')
            ->join('production_material_usage_items as ui', 'ui.production_material_usage_id', '=', 'u.id')
            ->where('u.production_work_order_id', $id)
            ->where('u.status', 'approved')
            ->groupBy('ui.product_id')
            ->select(
                'ui.product_id',
                DB::raw('SUM(ui.actual_qty) as actual_qty'),
                DB::raw('SUM(ui.base_actual_qty) as actual_base_qty'),
                DB::raw('SUM(ui.total_cost) as total_cost'),
                DB::raw('CASE WHEN SUM(ui.base_actual_qty) > 0 THEN SUM(ui.total_cost) / SUM(ui.base_actual_qty) ELSE 0 END as unit_cost')
            )
            ->get()
            ->keyBy('product_id');

        $materials = $items->map(function ($item) use ($wo, $actualItems) {
            $factor = 1.0;
            if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                $factor = (float) DB::table('product_unit_conversions')
                    ->where('product_id', $item->product_id)
                    ->where('unit_id', $item->unit_id)
                    ->where('is_active', 1)
                    ->value('conversion_factor');
            }

            $unitCost = (float) (DB::table('warehouses_stocks')
                ->where('entity_id', $wo->entity_id)
                ->where('warehouse_id', $wo->warehouse_id)
                ->where('product_id', $item->product_id)
                ->value('avg_cost') ?? 0);

            $actual = $actualItems->get($item->product_id);

            return [
                'sku' => $item->sku,
                'name' => $item->product_name,
                'qty' => round((float) $item->qty * (float) $wo->batch_qty, 3),
                'unit' => $item->unit_code ?: $item->unit_name,
                'base_qty' => round((float) $item->qty * $factor * (float) $wo->batch_qty, 3),
                'unit_cost' => $unitCost,
                'line_cost' => round((float) $item->qty * (float) $wo->batch_qty * $unitCost, 2),
                'actual_qty' => $actual ? (float) $actual->actual_qty : null,
                'actual_unit_cost' => $actual ? (float) $actual->unit_cost : null,
                'actual_cost' => $actual ? (float) $actual->total_cost : null,
            ];
        })->values();

        $materialTotal = (float) $materials->sum('line_cost');
        $estimatedTotal = $materialTotal + (float) $woCosts->sum('amount');

        $workers = $workers->map(function ($worker) use ($woCosts) {
            $worker->estimated_cost = (float) $woCosts
                ->where('cost_group', 'U')
                ->where('worker_id', $worker->worker_id)
                ->sum('amount');
            return $worker;
        });

        return view('inventori.produksi.work-order.show', compact('wo', 'workers', 'materials', 'woCosts', 'materialTotal', 'estimatedTotal'));
    }

    public function saveProductionResult(Request $request, int $id)
    {
        $action = (string) $request->input('action', 'close');
        $resultIdsToPost = collect();

        // Input langsung dari list SPK: hasil bagus/reject dicatat per pekerja.
        if ($request->has('good_qty') && $request->has('reject_by_worker')) {
            $entry = $request->validate([
                'production_date' => ['required', 'date'],
                'worker_id' => ['required', 'array', 'min:1'],
                'worker_id.*' => ['required', 'integer', 'distinct'],
                'good_qty' => ['required', 'array'],
                'good_qty.*' => ['required', 'numeric', 'gte:0'],
                'reject_by_worker' => ['required', 'array'],
                'reject_by_worker.*' => ['required', 'numeric', 'gte:0'],
            ]);
            abort_unless(count($entry['worker_id']) === count($entry['good_qty'])
                && count($entry['worker_id']) === count($entry['reject_by_worker']), 422, 'Data hasil per pekerja tidak lengkap.');

            $entityIdForResult = $this->entityId();
            $woForResult = DB::table('production_work_orders')
                ->where('entity_id', $entityIdForResult)->where('id', $id)->first();
            abort_unless($woForResult, 404, 'SPK tidak ditemukan.');
            abort_unless($woForResult->status === 'in_progress', 422, 'Hanya SPK yang sedang diproses dapat menerima hasil produksi.');

            $setup = DB::table('production_work_order_workers as wow')
                ->join('production_work_order_costs as c', function ($join) {
                    $join->on('c.production_work_order_id', '=', 'wow.production_work_order_id')
                        ->on('c.worker_id', '=', 'wow.worker_id')
                        ->where('c.cost_group', '=', 'U');
                })
                ->where('wow.production_work_order_id', $id)
                ->get(['wow.worker_id', 'c.pay_type', 'c.unit_rate', 'c.amount'])
                ->keyBy('worker_id');
            abort_unless($setup->count() === count($entry['worker_id']), 422, 'Daftar pekerja tidak sesuai setup SPK.');
            foreach ($entry['worker_id'] as $workerId) {
                abort_unless($setup->has($workerId), 422, 'Pekerja hasil tidak ditemukan pada setup SPK.');
            }

            DB::transaction(function () use ($entry, $setup, $woForResult, $entityIdForResult, $id) {
                $result = DB::table('production_work_order_results')
                    ->where('production_work_order_id', $id)
                    ->where('production_date', $entry['production_date'])
                    ->where('status', 'draft')
                    ->lockForUpdate()
                    ->first();

                if ($result) {
                    $resultId = $result->id;
                    DB::table('production_work_order_result_lines')->where('production_work_order_result_id', $resultId)->delete();
                    DB::table('production_work_order_results')->where('id', $resultId)->update([
                        'updated_at' => now(),
                    ]);
                } else {
                    $resultId = DB::table('production_work_order_results')->insertGetId([
                        'entity_id' => $entityIdForResult,
                        'production_work_order_id' => $id,
                        'production_date' => $entry['production_date'],
                        'status' => 'draft',
                        'created_by' => auth()->id(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                foreach ($entry['worker_id'] as $i => $workerId) {
                    $cost = $setup->get($workerId);
                    DB::table('production_work_order_result_lines')->insert([
                        'production_work_order_result_id' => $resultId,
                        'worker_id' => $workerId,
                        'pay_type' => strtolower((string) $cost->pay_type) === 'satuan' ? 'satuan' : 'borongan',
                        'unit_rate' => round((float) ($cost->unit_rate ?? $cost->amount), 2),
                        'good_qty' => round((float) $entry['good_qty'][$i], 3),
                        'reject_qty' => round((float) $entry['reject_by_worker'][$i], 3),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            if ($action === 'save') {
                return redirect()->route('produksi.work-order')
                    ->with('success', 'Hasil produksi berhasil disimpan. Stok dan jurnal belum diposting.');
            }

            $draftResults = DB::table('production_work_order_results')
                ->where('production_work_order_id', $id)
                ->where('status', 'draft')
                ->orderBy('production_date')
                ->get();
            $resultIdsToPost = $draftResults->pluck('id');
            $lines = DB::table('production_work_order_result_lines')
                ->whereIn('production_work_order_result_id', $resultIdsToPost)
                ->get();

            $goodTotal = (float) $lines->sum('good_qty');
            $rejectTotal = (float) $lines->sum('reject_qty');
            $workerIds = array_values($entry['worker_id']);
            $basis = [];
            $rates = [];
            $laborQty = [];
            foreach ($workerIds as $workerId) {
                $cost = $setup->get($workerId);
                $basis[] = strtolower((string) $cost->pay_type) === 'satuan' ? 'BIJI' : 'BORONGAN';
                $rates[] = round((float) ($cost->unit_rate ?? $cost->amount), 2);
                $workerLines = $lines->where('worker_id', $workerId);
                $laborQty[] = strtolower((string) $cost->pay_type) === 'satuan'
                    ? (float) $workerLines->sum('good_qty') + (float) $workerLines->sum('reject_qty')
                    : 1;
            }

            $request->merge([
                'worker_id' => $workerIds,
                'labor_basis' => $basis,
                'labor_rate' => $rates,
                'labor_qty' => $laborQty,
                'good_output_qty' => $goodTotal,
                'reject_qty' => $rejectTotal,
            ]);
        }

        $request->merge([
            'labor_rate' => collect($request->input('labor_rate', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
            'labor_qty' => collect($request->input('labor_qty', []))->map(fn ($value) => FormatHelper::parse($value))->all(),
            'good_output_qty' => FormatHelper::parse($request->input('good_output_qty')),
            'reject_qty' => FormatHelper::parse($request->input('reject_qty')),
        ]);

        $data = $request->validate([
            'worker_id' => ['required', 'array', 'min:1'],
            'worker_id.*' => ['required', 'integer', 'distinct'],
            'labor_basis' => ['required', 'array', 'min:1'],
            'labor_basis.*' => ['required', 'in:BIJI,BORONGAN'],
            'labor_rate' => ['required', 'array', 'min:1'],
            'labor_rate.*' => ['required', 'numeric', 'gt:0'],
            'labor_qty' => ['required', 'array', 'min:1'],
            'labor_qty.*' => ['required', 'numeric', 'gte:0'],
            'good_output_qty' => ['required', 'numeric', 'gt:0'],
            'reject_qty' => ['required', 'numeric', 'gte:0'],
        ]);

        abort_unless(count($data['worker_id']) === count($data['labor_basis'])
            && count($data['worker_id']) === count($data['labor_rate'])
            && count($data['worker_id']) === count($data['labor_qty']), 422, 'Data upah pekerja tidak lengkap.');

        $postingDate = $resultIdsToPost->isNotEmpty()
            ? (DB::table('production_work_order_results')->whereIn('id', $resultIdsToPost)->max('production_date') ?: now()->toDateString())
            : now()->toDateString();
        $entityId = $this->entityId();

        DB::transaction(function () use ($data, $entityId, $id, $postingDate, $resultIdsToPost): void {
            $wo = DB::table('production_work_orders')
                ->where('entity_id', $entityId)
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            abort_unless($wo, 404, 'SPK tidak ditemukan.');
            abort_if($wo->status !== 'in_progress', 422, 'Hanya SPK On Progress yang dapat diselesaikan.');

            $alreadyPosted = DB::table('productions')
                ->where('production_work_order_id', $wo->id)
                ->exists();
            abort_if($alreadyPosted, 422, 'Hasil produksi SPK ini sudah diposting.');

            $hasOpenMaterialUsage = DB::table('production_wo_material_usages')
                ->where('production_work_order_id', $wo->id)
                ->whereIn('status', ['draft', 'pending'])
                ->exists();
            abort_if($hasOpenMaterialUsage, 422, 'Selesaikan atau tolak pemakaian bahan yang masih Draft/Menunggu Approval sebelum SPK ditutup.');

            $goodQty = round((float) $data['good_output_qty'], 3);
            $rejectQty = round((float) $data['reject_qty'], 3);
            $targetQty = round((float) $wo->target_output_qty, 3);

            abort_if($goodQty <= 0, 422, 'Hasil bagus harus lebih dari 0.');
            abort_if(($goodQty + $rejectQty) > ($targetQty + 0.000001), 422, 'Hasil bagus + reject melebihi target produksi.');

            $approvedUsageIds = DB::table('production_wo_material_usages')
                ->where('production_work_order_id', $wo->id)
                ->where('status', 'approved')
                ->lockForUpdate()
                ->pluck('id');
            abort_unless($approvedUsageIds->isNotEmpty(), 422, 'Pemakaian bahan SPK harus sudah disetujui sebelum hasil produksi diposting.');

            $materialCost = (float) DB::table('production_material_usage_items')
                ->whereIn('production_material_usage_id', $approvedUsageIds)
                ->sum('total_cost');

            $workers = DB::table('production_work_order_workers as wow')
                ->join('workers as w', 'w.id', '=', 'wow.worker_id')
                ->where('wow.production_work_order_id', $wo->id)
                ->whereIn('wow.worker_id', $data['worker_id'])
                ->get(['wow.id as worker_row_id', 'wow.worker_id', 'w.name']);

            abort_unless($workers->count() === count($data['worker_id']), 422, 'Pekerja hasil produksi tidak sesuai dengan SPK.');

            $laborCost = 0.0;
            $laborRows = [];
            foreach ($data['worker_id'] as $i => $workerId) {
                $rate = round((float) $data['labor_rate'][$i], 2);
                $qty = round((float) $data['labor_qty'][$i], 3);
                $amount = round($rate * $qty, 2);
                $worker = $workers->firstWhere('worker_id', (int) $workerId);

                $laborCost += $amount;
                $laborRows[] = [
                    'worker_row_id' => $worker->worker_row_id,
                    'worker_id' => $worker->worker_id,
                    'worker_name' => $worker->name,
                    'basis' => $data['labor_basis'][$i],
                    'rate' => $rate,
                    'qty' => $qty,
                    'amount' => $amount,
                ];
            }

            $otherCosts = DB::table('production_work_order_costs')
                ->where('production_work_order_id', $wo->id)
                ->whereIn('cost_group', ['A', 'S', 'O'])
                ->sum('amount');

            $totalCost = round($materialCost + $laborCost + (float) $otherCosts, 2);
            $rejectCost = $targetQty > 0 ? round($totalCost * ($rejectQty / $targetQty), 2) : 0.0;
            $goodCost = round($totalCost - $rejectCost, 2);
            $goodUnitCost = $goodQty > 0 ? round($goodCost / $goodQty, 6) : 0.0;

            $finished = DB::table('products')
                ->where('entity_id', $entityId)
                ->where('id', DB::table('boms')->where('id', $wo->bom_id)->value('product_id'))
                ->first();
            abort_unless($finished, 422, 'Produk hasil BOM tidak valid.');

            $stock = DB::table('warehouses_stocks')
                ->where('entity_id', $entityId)
                ->where('warehouse_id', $wo->warehouse_id)
                ->where('product_id', $finished->id)
                ->lockForUpdate()
                ->first();

            if ($stock) {
                $oldQty = (float) $stock->qty;
                $oldAvg = (float) $stock->avg_cost;
                $newQty = $oldQty + $goodQty;
                $newAvg = $newQty > 0
                    ? (($oldQty * $oldAvg) + ($goodQty * $goodUnitCost)) / $newQty
                    : 0;

                DB::table('warehouses_stocks')->where('id', $stock->id)->update([
                    'qty' => $newQty,
                    'avg_cost' => round($newAvg, 9),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('warehouses_stocks')->insert([
                    'entity_id' => $entityId,
                    'warehouse_id' => $wo->warehouse_id,
                    'product_id' => $finished->id,
                    'qty' => $goodQty,
                    'avg_cost' => round($goodUnitCost, 9),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $productionId = DB::table('productions')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $wo->business_unit_id,
                'warehouse_id' => $wo->warehouse_id,
                'bom_id' => $wo->bom_id,
                'production_work_order_id' => $wo->id,
                'user_id' => auth()->id(),
                'production_no' => 'PROD-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'production_date' => $postingDate.' 00:00:00',
                'qty' => $wo->batch_qty,
                'total_cost' => $totalCost,
                'good_output_qty' => $goodQty,
                'reject_qty' => $rejectQty,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($laborRows as $labor) {
                DB::table('production_costs')->insert([
                    'production_id' => $productionId,
                    'cost_group' => 'U',
                    'description' => 'Upah '.$labor['worker_name'].' ('.$labor['basis'].')',
                    'amount' => $labor['amount'],
                    'source' => 'work_order_result',
                    'reference_type' => 'production_work_order_worker',
                    'reference_id' => $labor['worker_row_id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $otherRows = DB::table('production_work_order_costs')
                ->where('production_work_order_id', $wo->id)
                ->whereIn('cost_group', ['A', 'S', 'O'])
                ->get();

            foreach ($otherRows as $cost) {
                DB::table('production_costs')->insert([
                    'production_id' => $productionId,
                    'cost_group' => $cost->cost_group,
                    'description' => $cost->description,
                    'amount' => round((float) $cost->amount, 2),
                    'source' => 'work_order_wip',
                    'reference_type' => 'production_work_order_cost',
                    'reference_id' => $cost->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('production_outputs')->insert([
                'production_id' => $productionId,
                'product_id' => $finished->id,
                'warehouse_id' => $wo->warehouse_id,
                'qty' => $goodQty,
                'unit_cost' => $goodUnitCost,
                'total_cost' => $goodCost,
                'output_type' => 'good',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($rejectQty > 0) {
                DB::table('production_rejects')->insert([
                    'production_id' => $productionId,
                    'product_id' => $finished->id,
                    'qty' => $rejectQty,
                    'reject_type' => 'scrap',
                    'description' => 'Reject produksi SPK '.$wo->wo_no,
                    'recoverable_value' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('stock_movements')->insert([
                'entity_id' => $entityId,
                'business_unit_id' => $wo->business_unit_id,
                'warehouse_id' => $wo->warehouse_id,
                'product_id' => $finished->id,
                'unit_id' => $finished->base_unit_id,
                'transaction_qty' => $goodQty,
                'conversion_factor' => 1,
                'movement_type' => 'production_in',
                'qty' => $goodQty,
                'unit_cost' => $goodUnitCost,
                'reference_type' => 'production_work_order',
                'reference_id' => $wo->id,
                'occurred_at' => $postingDate.' 00:00:00',
                'created_by' => auth()->id() ?? 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $directLaborAccount = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)
                ->where('mapping_key', 'direct_labor')->value('account_id');
            $salaryPayableAccount = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)
                ->where('mapping_key', 'salary_payable')->value('account_id');
            $finishedInventoryAccount = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)
                ->where('mapping_key', 'inventory_finished_goods')->value('account_id');
            $damageAccount = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)
                ->where('mapping_key', 'inventory_damage_loss')->value('account_id');

            $laborJournalId = DB::table('journals')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $wo->business_unit_id,
                'journal_no' => 'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'journal_date' => $postingDate,
                'source_type' => 'PRODUCTION_LABOR',
                'source_id' => $productionId,
                'description' => 'Pengakuan upah produksi '.$wo->wo_no,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_entries')->insert([
                ['journal_id' => $laborJournalId, 'account_id' => $directLaborAccount, 'debit' => $laborCost, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
                ['journal_id' => $laborJournalId, 'account_id' => $salaryPayableAccount, 'debit' => 0, 'credit' => $laborCost, 'created_at' => now(), 'updated_at' => now()],
            ]);

            $costAccounts = [
                'B' => DB::table('business_unit_account_mappings')->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)->where('mapping_key', 'direct_material')->value('account_id'),
                'U' => $directLaborAccount,
                'A' => DB::table('business_unit_account_mappings')->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)->where('mapping_key', 'direct_equipment')->value('account_id'),
                'S' => DB::table('business_unit_account_mappings')->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)->where('mapping_key', 'direct_rent')->value('account_id'),
                'O' => DB::table('business_unit_account_mappings')->where('entity_id', $entityId)->where('business_unit_id', $wo->business_unit_id)->where('mapping_key', 'direct_overhead')->value('account_id'),
            ];

            abort_unless(!in_array(null, $costAccounts, true), 422, 'Mapping akun HPP produksi belum lengkap.');

            $goodShare = $targetQty > 0 ? ($goodCost / $totalCost) : 0;
            $rejectShare = $targetQty > 0 ? ($rejectCost / $totalCost) : 0;
            $hppJournalId = DB::table('journals')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $wo->business_unit_id,
                'journal_no' => 'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'journal_date' => now()->toDateString(),
                'source_type' => 'PRODUCTION_HPP',
                'source_id' => $productionId,
                'description' => 'Kapitalisasi HPP produksi '.$wo->wo_no,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $hppEntries = [
                ['journal_id' => $hppJournalId, 'account_id' => $finishedInventoryAccount, 'debit' => $goodCost, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()],
            ];
            foreach ($costAccounts as $group => $accountId) {
                $groupTotal = match ($group) {
                    'B' => $materialCost,
                    'U' => $laborCost,
                    'A', 'S', 'O' => (float) $otherRows->where('cost_group', $group)->sum('amount'),
                };
                $goodGroup = round($groupTotal * $goodShare, 2);
                $rejectGroup = round($groupTotal * $rejectShare, 2);
                if ($goodGroup > 0) {
                    $hppEntries[] = ['journal_id' => $hppJournalId, 'account_id' => $accountId, 'debit' => 0, 'credit' => $goodGroup, 'created_at' => now(), 'updated_at' => now()];
                }
                if ($rejectGroup > 0) {
                    $hppEntries[] = ['journal_id' => $hppJournalId, 'account_id' => $accountId, 'debit' => 0, 'credit' => $rejectGroup, 'created_at' => now(), 'updated_at' => now()];
                }
            }
            if ($rejectCost > 0) {
                $hppEntries[] = ['journal_id' => $hppJournalId, 'account_id' => $damageAccount, 'debit' => $rejectCost, 'credit' => 0, 'created_at' => now(), 'updated_at' => now()];
            }

            DB::table('journal_entries')->insert($hppEntries);

            DB::table('productions')->where('id', $productionId)->update([
                'updated_at' => now(),
            ]);

            DB::table('production_work_orders')->where('id', $wo->id)->update([
                'status' => 'completed',
                'updated_at' => now(),
            ]);

            if ($resultIdsToPost->isNotEmpty()) {
                DB::table('production_work_order_results')->whereIn('id', $resultIdsToPost)->update([
                    'status' => 'posted',
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('produksi.work-order')
            ->with('success', 'Closing SPK berhasil. Stok barang jadi, upah, HPP, dan reject telah diproses.');
    }

    public function resultsReport(Request $request)
    {
        $entityId = $this->entityId();
        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $results = DB::table('production_work_order_results as r')
            ->join('production_work_orders as wo', 'wo.id', '=', 'r.production_work_order_id')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->leftJoin('production_work_order_result_lines as l', 'l.production_work_order_result_id', '=', 'r.id')
            ->leftJoin('workers as w', 'w.id', '=', 'l.worker_id')
            ->where('r.entity_id', $entityId)
            ->whereDate('r.production_date', '>=', $dateFrom)
            ->whereDate('r.production_date', '<=', $dateTo)
            ->groupBy('r.id', 'r.production_date', 'r.status', 'wo.id', 'wo.wo_no', 'p.name')
            ->orderByDesc('r.production_date')
            ->orderByDesc('r.id')
            ->get([
                'r.id', 'r.production_date', 'r.status',
                'wo.id as work_order_id', 'wo.wo_no', 'p.name as product_name',
                DB::raw('SUM(l.good_qty) as good_qty'),
                DB::raw('SUM(l.reject_qty) as reject_qty'),
                DB::raw("SUM(CASE WHEN l.pay_type = 'satuan' THEN (l.good_qty + l.reject_qty) * l.unit_rate ELSE 0 END) as unit_labor_cost"),
                DB::raw('COUNT(DISTINCT l.worker_id) as worker_count'),
            ]);

        $lines = DB::table('production_work_order_result_lines as l')
            ->join('production_work_order_results as r', 'r.id', '=', 'l.production_work_order_result_id')
            ->join('workers as w', 'w.id', '=', 'l.worker_id')
            ->where('r.entity_id', $entityId)
            ->whereDate('r.production_date', '>=', $dateFrom)
            ->whereDate('r.production_date', '<=', $dateTo)
            ->orderByDesc('r.production_date')
            ->orderBy('w.name')
            ->get([
                'r.id as result_id', 'r.production_date', 'r.status',
                'l.worker_id', 'w.name as worker_name', 'l.pay_type', 'l.unit_rate',
                'l.good_qty', 'l.reject_qty',
                DB::raw("CASE WHEN l.pay_type = 'satuan' THEN l.good_qty * l.unit_rate ELSE 0 END as good_cost"),
                DB::raw("CASE WHEN l.pay_type = 'satuan' THEN l.reject_qty * l.unit_rate ELSE 0 END as reject_cost"),
            ]);

        return view('inventori.produksi.work-order.hasil-report', compact('results', 'lines', 'dateFrom', 'dateTo'));
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

        if (request()->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'SPK Open berhasil dihapus.']);
        }

        return redirect()->route('produksi.work-order')->with('success', 'SPK Open berhasil dihapus.');
    }

    private function materialProgressPercent(int $workOrderId, int $bomId, float $batchQty): float
    {
        $planned = DB::table('bom_items as bi')
            ->join('products as p', 'p.id', '=', 'bi.product_id')
            ->leftJoin('product_unit_conversions as puc', function ($join) {
                $join->on('puc.product_id', '=', 'bi.product_id')
                    ->on('puc.unit_id', '=', 'bi.unit_id')
                    ->where('puc.is_active', 1);
            })
            ->where('bi.bom_id', $bomId)
            ->groupBy('bi.product_id')
            ->get([
                'bi.product_id',
                DB::raw('SUM(bi.qty * CASE WHEN bi.unit_id = p.base_unit_id THEN 1 ELSE COALESCE(puc.conversion_factor, 1) END) as planned_base_qty'),
            ]);

        if ($planned->isEmpty()) {
            return 0.0;
        }

        $used = DB::table('production_wo_material_usages as u')
            ->join('production_material_usage_items as ui', 'ui.production_material_usage_id', '=', 'u.id')
            ->where('u.production_work_order_id', $workOrderId)
            ->where('u.status', 'approved')
            ->groupBy('ui.product_id')
            ->pluck(DB::raw('SUM(ui.base_actual_qty)'), 'ui.product_id');

        $percentages = $planned->map(function ($material) use ($used, $batchQty) {
            $plannedQty = (float) $material->planned_base_qty * $batchQty;
            if ($plannedQty <= 0) {
                return null;
            }

            return min(100, ((float) ($used[$material->product_id] ?? 0) / $plannedQty) * 100);
        })->filter(fn ($value) => $value !== null);

        return $percentages->isNotEmpty() ? round($percentages->avg(), 1) : 0.0;
    }

    private function workOrderRow(int $entityId, int $id): array
    {
        $row = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $entityId)
            ->where('wo.id', $id)
            ->first([
                'wo.id',
                'wo.wo_no',
                'wo.wo_date',
                'wo.target_output_qty',
                'wo.batch_qty',
                'wo.bom_id',
                'wo.business_unit_id',
                'wo.warehouse_id',
                'wo.notes',
                'wo.status',
                'b.code as bom_code',
                'p.name as product_name',
                'w.name as warehouse_name',
            ]);

        abort_unless($row, 404);

        return [
            'id' => $row->id,
            'wo_no' => $row->wo_no,
            'wo_date' => $row->wo_date,
            'wo_date_display' => Carbon::parse($row->wo_date)->format('d/m/Y'),
            'product_name' => $row->product_name,
            'bom_code' => $row->bom_code,
            'warehouse_name' => $row->warehouse_name,
            'target_output_qty' => (float) $row->target_output_qty,
            'material_percent' => $this->materialProgressPercent(
                (int) $row->id,
                (int) $row->bom_id,
                (float) $row->batch_qty
            ),
            'good_output_qty' => (float) DB::table('productions')
                ->where('production_work_order_id', $row->id)
                ->where('status', 'posted')
                ->sum('good_output_qty'),
            'worker_count' => (int) DB::table('production_work_order_workers')
                ->where('production_work_order_id', $row->id)
                ->count(),
            'status' => $row->status,
            'print_url' => route('produksi.work-order.print', $row->id),
            'show_url' => route('produksi.work-order.show', $row->id),
        ];
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
