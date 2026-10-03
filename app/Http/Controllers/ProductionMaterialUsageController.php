<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionMaterialUsageController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId();
        abort_unless($entityId, 422, 'Entitas belum tersedia.');

        $dateFrom = $request->input('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->input('date_to', now()->endOfMonth()->toDateString());

        $rows = DB::table('production_wo_material_usages as u')
            ->join('production_work_orders as wo', 'wo.id', '=', 'u.production_work_order_id')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'u.warehouse_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'u.user_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'u.approved_by')
            ->where('u.entity_id', $entityId)
            ->whereDate('u.usage_date', '>=', $dateFrom)
            ->whereDate('u.usage_date', '<=', $dateTo)
            ->when($request->filled('status'), fn ($q) => $q->where('u.status', $request->string('status')->toString()))
            ->select(
                'u.*',
                'wo.wo_no',
                'p.name as product_name',
                'w.name as warehouse_name',
                'creator.name as creator_name',
                'approver.name as approver_name'
            )
            ->orderByDesc('u.usage_date')
            ->orderByDesc('u.id')
            ->get();

        $workOrders = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $entityId)
            ->where('wo.status', 'in_progress')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('production_wo_material_usages as u')
                    ->whereColumn('u.production_work_order_id', 'wo.id')
                    ->whereIn('u.status', ['draft', 'pending', 'approved']);
            })
            ->orderByDesc('wo.wo_date')
            ->orderByDesc('wo.id')
            ->get([
                'wo.id',
                'wo.wo_no',
                'wo.wo_date',
                'wo.target_output_qty',
                'wo.warehouse_id',
                'b.code as bom_code',
                'p.name as product_name',
                'w.name as warehouse_name',
            ]);

        return view('inventori.produksi.pemakaian-bahan.index', compact(
            'rows', 'workOrders', 'dateFrom', 'dateTo'
        ));
    }

    public function create(int $workOrderId)
    {
        $entityId = $this->entityId();

        $wo = DB::table('production_work_orders as wo')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'wo.warehouse_id')
            ->where('wo.entity_id', $entityId)
            ->where('wo.id', $workOrderId)
            ->where('wo.status', 'in_progress')
            ->first([
                'wo.*',
                'b.code as bom_code',
                'b.name as bom_name',
                'p.name as product_name',
                'w.name as warehouse_name',
            ]);

        abort_unless($wo, 404, 'SPK On Progress tidak ditemukan.');

        $exists = DB::table('production_wo_material_usages')
            ->where('production_work_order_id', $workOrderId)
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->exists();

        abort_if($exists, 422, 'Pemakaian bahan untuk SPK ini sudah tersedia.');

        $items = $this->plannedMaterials($wo);

        return view('inventori.produksi.pemakaian-bahan.create', compact('wo', 'items'));
    }

    private function plannedMaterials($wo)
    {
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

        return $items->map(function ($item) use ($wo) {
            $factor = 1.0;
            if ((int) $item->unit_id !== (int) $item->base_unit_id) {
                $factor = (float) DB::table('product_unit_conversions')
                    ->where('product_id', $item->product_id)
                    ->where('unit_id', $item->unit_id)
                    ->where('is_active', 1)
                    ->value('conversion_factor');
            }

            return [
                'product_id' => (int) $item->product_id,
                'unit_id' => (int) $item->unit_id,
                'sku' => $item->sku,
                'name' => $item->product_name,
                'planned_qty' => round((float) $item->qty * (float) $wo->batch_qty, 3),
                'unit' => $item->unit_code ?: $item->unit_name,
                'factor' => $factor > 0 ? $factor : 1.0,
            ];
        })->values();
    }

    public function store(Request $request, int $workOrderId)
    {
        $data = $request->validate([
            'usage_date' => ['required', 'date'],
            'product_id' => ['required', 'array', 'min:1'],
            'product_id.*' => ['required', 'integer'],
            'actual_qty' => ['required', 'array', 'min:1'],
            'actual_qty.*' => ['required', 'numeric', 'gt:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $entityId = $this->entityId();

        DB::transaction(function () use ($data, $workOrderId, $entityId): void {
            $wo = DB::table('production_work_orders')
                ->where('entity_id', $entityId)
                ->where('id', $workOrderId)
                ->where('status', 'in_progress')
                ->lockForUpdate()
                ->first();

            abort_unless($wo, 422, 'SPK harus berstatus On Progress.');

            $existing = DB::table('production_wo_material_usages')
                ->where('production_work_order_id', $workOrderId)
                ->whereIn('status', ['draft', 'pending', 'approved'])
                ->exists();
            abort_if($existing, 422, 'Pemakaian bahan untuk SPK ini sudah tersedia.');

            $planned = $this->plannedMaterials($wo)->keyBy('product_id');

            $usageId = DB::table('production_wo_material_usages')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $wo->business_unit_id,
                'warehouse_id' => $wo->warehouse_id,
                'production_work_order_id' => $wo->id,
                'user_id' => auth()->id(),
                'usage_no' => 'PB-'.now()->format('YmdHis').'-'.Str::upper(Str::random(3)),
                'usage_date' => $data['usage_date'],
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($data['product_id'] as $i => $productId) {
                $p = $planned->get((int) $productId);
                abort_unless($p, 422, 'Material tidak sesuai dengan BOM SPK.');

                $actual = (float) $data['actual_qty'][$i];
                DB::table('production_material_usage_items')->insert([
                    'production_material_usage_id' => $usageId,
                    'product_id' => $p['product_id'],
                    'unit_id' => $p['unit_id'],
                    'planned_qty' => $p['planned_qty'],
                    'actual_qty' => $actual,
                    'conversion_factor' => $p['factor'],
                    'base_actual_qty' => round($actual * $p['factor'], 6),
                    'unit_cost' => 0,
                    'total_cost' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        return redirect()->route('produksi.pemakaian-bahan')
            ->with('success', 'Pemakaian bahan berhasil dibuat sebagai Draft.');
    }

    public function show(int $id)
    {
        $entityId = $this->entityId();

        $usage = DB::table('production_wo_material_usages as u')
            ->join('production_work_orders as wo', 'wo.id', '=', 'u.production_work_order_id')
            ->join('boms as b', 'b.id', '=', 'wo.bom_id')
            ->join('products as p', 'p.id', '=', 'b.product_id')
            ->join('warehouses as w', 'w.id', '=', 'u.warehouse_id')
            ->leftJoin('users as creator', 'creator.id', '=', 'u.user_id')
            ->leftJoin('users as approver', 'approver.id', '=', 'u.approved_by')
            ->where('u.entity_id', $entityId)
            ->where('u.id', $id)
            ->first([
                'u.*',
                'wo.wo_no',
                'wo.target_output_qty',
                'b.code as bom_code',
                'p.name as product_name',
                'w.name as warehouse_name',
                'creator.name as creator_name',
                'approver.name as approver_name',
            ]);

        abort_unless($usage, 404);

        $items = DB::table('production_material_usage_items as ui')
            ->join('products as p', 'p.id', '=', 'ui.product_id')
            ->leftJoin('units as u', 'u.id', '=', 'ui.unit_id')
            ->where('ui.production_material_usage_id', $id)
            ->orderBy('ui.id')
            ->get([
                'ui.*',
                'p.sku',
                'p.name as product_name',
                'u.code as unit_code',
                'u.name as unit_name',
            ]);

        $journals = collect();

        if (!empty($usage->journal_id)) {
            $journals = DB::table('journals')
                ->where('id', $usage->journal_id)
                ->where('entity_id', $entityId)
                ->where('source_type', 'PRODUCTION_MATERIAL_USAGE')
                ->get();

            foreach ($journals as $journal) {
                $journal->entries = DB::table('journal_entries as je')
                    ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.account_id')
                    ->where('je.journal_id', $journal->id)
                    ->select('je.*', 'coa.code as account_code', 'coa.name as account_name')
                    ->orderBy('je.id')
                    ->get();
            }
        }

        return view('inventori.produksi.pemakaian-bahan.show', compact('usage', 'items', 'journals'));
    }

    public function submit(int $id)
    {
        $entityId = $this->entityId();
        $usage = DB::table('production_wo_material_usages')
            ->where('entity_id', $entityId)
            ->where('id', $id)
            ->where('status', 'draft')
            ->first();

        abort_unless($usage, 422, 'Hanya Draft yang dapat diajukan.');

        DB::table('production_wo_material_usages')->where('id', $id)->update([
            'status' => 'pending',
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Pemakaian bahan diajukan untuk approval.');
    }

    public function approve(int $id)
    {
        $entityId = $this->entityId();

        DB::transaction(function () use ($id, $entityId): void {
            $usage = DB::table('production_wo_material_usages')
                ->where('entity_id', $entityId)
                ->where('id', $id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            abort_unless($usage, 422, 'Pemakaian bahan tidak valid untuk approval.');

            $items = DB::table('production_material_usage_items as ui')
                ->join('products as p', 'p.id', '=', 'ui.product_id')
                ->where('ui.production_material_usage_id', $usage->id)
                ->get([
                    'ui.*',
                    'p.name as product_name',
                ]);

            // Mapping COA mengikuti tipe Unit Bisnis Produksi.
            $businessUnit = DB::table('business_units')->where('entity_id', $entityId)->where('id', $usage->business_unit_id)->first(['id', 'business_type']);
            abort_unless($businessUnit && $businessUnit->business_type === 'production', 422, 'Unit Bisnis pemakaian bahan harus bertipe Produksi.');

            $mapping = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $usage->business_unit_id)
                ->where('mapping_key', 'direct_material')
                ->value('account_id');

            $inventoryAccount = DB::table('business_unit_account_mappings')
                ->where('entity_id', $entityId)
                ->where('business_unit_id', $usage->business_unit_id)
                ->where('mapping_key', 'inventory')
                ->value('account_id');

            abort_unless($mapping, 422, 'Mapping akun Bahan Baku Langsung belum tersedia.');
            abort_unless($inventoryAccount, 422, 'Mapping akun Persediaan belum tersedia.');

            $total = 0.0;

            foreach ($items as $item) {
                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entityId)
                    ->where('warehouse_id', $usage->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                abort_unless($stock && (float) $stock->qty >= (float) $item->base_actual_qty, 422,
                    'Stok '.$item->product_name.' tidak mencukupi.');

                $unitCost = (float) $stock->avg_cost;
                $lineTotal = round((float) $item->base_actual_qty * $unitCost, 2);
                $total += $lineTotal;

                DB::table('production_material_usage_items')
                    ->where('id', $item->id)
                    ->update([
                        'unit_cost' => $unitCost,
                        'total_cost' => $lineTotal,
                        'updated_at' => now(),
                    ]);

                DB::table('warehouses_stocks')
                    ->where('id', $stock->id)
                    ->decrement('qty', $item->base_actual_qty);

                DB::table('stock_movements')->insert([
                    'entity_id' => $entityId,
                    'business_unit_id' => $usage->business_unit_id,
                    'warehouse_id' => $usage->warehouse_id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'transaction_qty' => $item->actual_qty,
                    'conversion_factor' => $item->conversion_factor,
                    'movement_type' => 'PRODUCTION_MATERIAL_OUT',
                    'qty' => -$item->base_actual_qty,
                    'unit_cost' => $unitCost,
                    'reference_type' => 'production_material_usage',
                    'reference_id' => $usage->id,
                    'occurred_at' => $usage->usage_date.' 00:00:00',
                    'created_by' => auth()->id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entityId,
                'business_unit_id' => $usage->business_unit_id,
                'journal_no' => 'JRN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)),
                'journal_date' => $usage->usage_date,
                'source_type' => 'production_material_usage',
                'source_id' => $usage->id,
                'description' => 'Pengakuan beban bahan baku '.$usage->usage_no,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('journal_entries')->insert([
                [
                    'journal_id' => $journalId,
                    'account_id' => $mapping,
                    'debit' => round($total, 2),
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'account_id' => $inventoryAccount,
                    'debit' => 0,
                    'credit' => round($total, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            DB::table('production_wo_material_usages')->where('id', $usage->id)->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'journal_id' => $journalId,
                'approved_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Pemakaian bahan disetujui. Beban bahan baku telah diakui.');
    }

    public function reject(Request $request, int $id)
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:3'],
        ]);

        $entityId = $this->entityId();
        $usage = DB::table('production_wo_material_usages')
            ->where('entity_id', $entityId)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        abort_unless($usage, 422, 'Hanya pemakaian Menunggu Approval yang dapat ditolak.');

        DB::table('production_wo_material_usages')->where('id', $id)->update([
            'status' => 'rejected',
            'rejection_reason' => $data['rejection_reason'],
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Pemakaian bahan ditolak.');
    }
}
