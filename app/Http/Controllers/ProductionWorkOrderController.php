<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ProductionWorkOrderExport;

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

        $workers = DB::table('workers')
            ->where('entity_id', $entityId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

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
            ->when($request->filled('date_from'), fn ($q) => $q->whereDate('wo.wo_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->whereDate('wo.wo_date', '<=', $request->date('date_to')))
            ->orderByDesc('wo.wo_date')
            ->orderByDesc('wo.id');

        $rows = $query->paginate(15)->withQueryString();

        $workerCounts = DB::table('production_work_order_workers')
            ->select('production_work_order_id', DB::raw('COUNT(*) as total_workers'))
            ->whereIn('production_work_order_id', $rows->pluck('id'))
            ->groupBy('production_work_order_id')
            ->pluck('total_workers', 'production_work_order_id');

        return view('inventori.produksi.work-order.index', compact(
            'boms',
            'warehouses',
            'workers',
            'rows',
            'workerCounts'
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'wo_date' => ['required', 'date'],
            'business_unit_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'bom_id' => ['required', 'integer'],
            'batch_qty' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
            'worker_id' => ['required', 'array', 'min:1'],
            'worker_id.*' => ['required', 'integer', 'distinct'],
            'worker_role' => ['nullable', 'array'],
            'worker_role.*' => ['nullable', 'string', 'max:100'],
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
                    'role' => trim((string) (($data['worker_role'] ?? [])[$i] ?? '')) ?: null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('produksi.work-order')->with('success', 'SPK berhasil dibuat.');
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
            ->select('wo.*', 'b.code as bom_code', 'b.name as bom_name', 'b.output_qty', 'p.name as product_name', 'p.code as product_code', 'w.code as warehouse_code', 'w.name as warehouse_name')
            ->first();

        abort_unless($wo, 404);

        $workers = DB::table('production_work_order_workers as wow')
            ->join('workers as w', 'w.id', '=', 'wow.worker_id')
            ->where('wow.production_work_order_id', $id)
            ->orderBy('w.name')
            ->get(['w.code', 'w.name', 'wow.role']);

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
