<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SalesReturnController extends Controller
{
    private function entityId(): int
    {
        $entity = DB::table('entities')->first();
        abort_unless($entity, 422, 'Entitas belum tersedia.');
        return (int) $entity->id;
    }

    private function mappingAccountId(int $entity, int $businessUnitId, string $mappingKey): int
    {
        $account = DB::table('business_unit_account_mappings as m')
            ->join('chart_of_accounts as c', 'c.id', '=', 'm.account_id')
            ->where('m.entity_id', $entity)
            ->where('m.business_unit_id', $businessUnitId)
            ->where('m.mapping_key', $mappingKey)
            ->where('c.is_active', 1)
            ->where('c.is_postable', 1)
            ->first(['c.id']);

        abort_unless(
            $account,
            422,
            'Mapping account tidak ditemukan: ' . $mappingKey . '.'
        );

        return (int) $account->id;
    }

    public function index()
    {
        $entity = $this->entityId();

        $warehouses = DB::table('warehouses')
            ->where('entity_id', $entity)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('inventori.penjualan.retur.index', compact('warehouses'));
    }

    public function data(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        $query = DB::table('sales_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->whereBetween('r.return_date', [$start . ' 00:00:00', $end . ' 23:59:59']);

        $recordsTotal = (clone $query)->count('r.id');

        $search = trim((string) $request->input('search.value', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%' . $search . '%';
                $q->where('r.return_no', 'like', $like)
                    ->orWhere('s.invoice_no', 'like', $like)
                    ->orWhere('c.name', 'like', $like)
                    ->orWhere('u.name', 'like', $like)
                    ->orWhere('w.name', 'like', $like);
            });
        }

        $recordsFiltered = (clone $query)->count('r.id');

        $columns = [
            0 => 'r.return_no',
            1 => 'r.return_date',
            2 => 's.invoice_no',
            3 => 'c.name',
            4 => 'w.name',
            5 => 'r.total',
            6 => 'u.name',
            7 => 'r.status',
        ];

        $orderColumn = (int) $request->input('order.0.column', 1);
        $orderDir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $query->orderBy($columns[$orderColumn] ?? 'r.return_date', $orderDir);

        $startRow = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 15);
        if ($length < 1) $length = 15;
        if ($length > 100) $length = 100;

        $rows = $query
            ->select(
                'r.id', 'r.return_no', 'r.return_date', 'r.total', 'r.status',
                's.invoice_no',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("COALESCE(w.name, '-') as warehouse_name"),
                DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.name ORDER BY p.name SEPARATOR ', ') FROM sales_return_items ri JOIN products p ON p.id = ri.product_id WHERE ri.sales_return_id = r.id) as product_names"),
                DB::raw("(SELECT COALESCE(SUM(ri.qty), 0) FROM sales_return_items ri WHERE ri.sales_return_id = r.id) as return_qty"),
                DB::raw("(SELECT GROUP_CONCAT(CONCAT(p.name, ' @ ', FORMAT(ri.unit_price, 2)) ORDER BY ri.id SEPARATOR ' | ') FROM sales_return_items ri JOIN products p ON p.id = ri.product_id WHERE ri.sales_return_id = r.id) as return_prices")
            )
            ->skip($startRow)
            ->take($length)
            ->get();

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data' => $rows,
        ]);
    }

    public function print(int $id)
    {
        $entity = $this->entityId();

        $return = DB::table('sales_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->leftJoin('business_units as bu', 'bu.id', '=', 'r.business_unit_id')
            ->where('r.entity_id', $entity)
            ->where('r.id', $id)
            ->select(
                'r.*',
                's.invoice_no',
                's.created_at as sale_created_at',
                DB::raw("COALESCE(c.name, 'Pelanggan Umum') as customer_name"),
                'c.address as customer_address',
                'c.phone as customer_phone',
                DB::raw("COALESCE(u.name, '-') as user_name"),
                DB::raw("COALESCE(w.name, '-') as warehouse_name"),
                'bu.name as business_unit_name'
            )
            ->first();

        abort_unless($return, 404, 'Retur penjualan tidak ditemukan.');

        $entityData = DB::table('entities')
            ->where('id', $entity)
            ->first(['name', 'address', 'phone']);

        $items = DB::table('sales_return_items as ri')
            ->join('products as p', 'p.id', '=', 'ri.product_id')
            ->leftJoin('units as un', 'un.id', '=', 'ri.unit_id')
            ->where('ri.sales_return_id', $return->id)
            ->select(
                'ri.*',
                'p.code as product_code',
                'p.name as product_name',
                DB::raw("COALESCE(un.name, '-') as unit_name")
            )
            ->orderBy('ri.id')
            ->get();

        $paid = (float) DB::table('payments')
            ->where('sale_id', $return->sale_id)
            ->sum('amount');

        $saleTotal = (float) DB::table('sales')
            ->where('id', $return->sale_id)
            ->value('total');

        $refundTo = $paid >= $saleTotal
            ? 'PENGEMBALIAN KAS / BANK'
            : 'PIUTANG USAHA';

        $terbilang = $this->terbilang((float) $return->total);

        return view('inventori.penjualan.retur.print', compact(
            'return',
            'items',
            'entityData',
            'refundTo',
            'terbilang'
        ));
    }

    private function terbilang(float $number): string
    {
        $number = (int) round($number);

        if ($number === 0) {
            return 'Nol';
        }

        $words = [
            '', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima',
            'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh',
            'Sebelas'
        ];

        if ($number < 12) {
            return $words[$number];
        }

        if ($number < 20) {
            return $this->terbilang($number - 10) . ' Belas';
        }

        if ($number < 100) {
            return $this->terbilang(intdiv($number, 10)) . ' Puluh'
                . ($number % 10 ? ' ' . $this->terbilang($number % 10) : '');
        }

        if ($number < 200) {
            return 'Seratus' . ($number % 100 ? ' ' . $this->terbilang($number % 100) : '');
        }

        if ($number < 1000) {
            return $this->terbilang(intdiv($number, 100)) . ' Ratus'
                . ($number % 100 ? ' ' . $this->terbilang($number % 100) : '');
        }

        if ($number < 2000) {
            return 'Seribu' . ($number % 1000 ? ' ' . $this->terbilang($number % 1000) : '');
        }

        if ($number < 1000000) {
            return $this->terbilang(intdiv($number, 1000)) . ' Ribu'
                . ($number % 1000 ? ' ' . $this->terbilang($number % 1000) : '');
        }

        if ($number < 1000000000) {
            return $this->terbilang(intdiv($number, 1000000)) . ' Juta'
                . ($number % 1000000 ? ' ' . $this->terbilang($number % 1000000) : '');
        }

        if ($number < 1000000000000) {
            return $this->terbilang(intdiv($number, 1000000000)) . ' Miliar'
                . ($number % 1000000000 ? ' ' . $this->terbilang($number % 1000000000) : '');
        }

        return $this->terbilang(intdiv($number, 1000000000000)) . ' Triliun'
            . ($number % 1000000000000 ? ' ' . $this->terbilang($number % 1000000000000) : '');
    }

    public function exportExcel(Request $request)
    {
        $entity = $this->entityId();
        $start = $request->input('start_date', now()->startOfMonth()->toDateString());
        $end = $request->input('end_date', now()->endOfMonth()->toDateString());

        abort_if(!$start || !$end || $start > $end, 422, 'Periode tanggal tidak valid.');

        $entityData = DB::table('entities')->where('id', $entity)->first(['name', 'address', 'phone']);

        $rows = DB::table('sales_returns as r')
            ->join('sales as s', 's.id', '=', 'r.sale_id')
            ->leftJoin('customers as c', 'c.id', '=', 'r.customer_id')
            ->leftJoin('users as u', 'u.id', '=', 'r.user_id')
            ->leftJoin('warehouses as w', 'w.id', '=', 'r.warehouse_id')
            ->where('r.entity_id', $entity)
            ->whereBetween('r.return_date', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->orderByDesc('r.return_date')
            ->select(
                'r.return_no', 'r.return_date', 's.invoice_no',
                DB::raw("COALESCE(c.name, 'Umum') as customer_name"),
                DB::raw("COALESCE(w.name, '-') as warehouse_name"),
                'r.total',
                DB::raw("COALESCE(u.name, '-') as user_name"),
                'r.status'
            )
            ->get();

        $safe = fn ($v) => e((string) ($v ?? ''));
        $date = fn ($v) => $v ? date('d/m/Y H:i', strtotime($v)) : '-';
        $status = fn ($v) => match (strtolower((string) $v)) {
            'posted' => 'Diposting',
            'draft' => 'Draf',
            'cancelled' => 'Dibatalkan',
            default => $v ?: '-',
        };
        $filename = 'retur-penjualan-' . $start . '-sd-' . $end . '.xls';

        $html = '<html><head><meta charset="UTF-8"></head><body>';
        $html .= '<table><tr><th colspan="8">' . $safe($entityData->name ?? 'MINI ERP') . '</th></tr>';
        if (!empty($entityData->address)) $html .= '<tr><td colspan="8">' . $safe($entityData->address) . '</td></tr>';
        if (!empty($entityData->phone)) $html .= '<tr><td colspan="8">Telp. ' . $safe($entityData->phone) . '</td></tr>';
        $html .= '<tr><th colspan="8">LAPORAN RETUR PENJUALAN</th></tr>';
        $html .= '<tr><td colspan="8">Periode: ' . $safe($start) . ' s/d ' . $safe($end) . '</td></tr>';
        $html .= '<tr><th>No. Retur</th><th>Tanggal</th><th>No. Struk</th><th>Pelanggan</th><th>Gudang</th><th>Total</th><th>Petugas</th><th>Status</th></tr>';

        foreach ($rows as $row) {
            $total = (float) $row->total;
            $html .= '<tr>';
            $html .= '<td>' . $safe($row->return_no) . '</td>';
            $html .= '<td>' . $safe($date($row->return_date)) . '</td>';
            $html .= '<td>' . $safe($row->invoice_no) . '</td>';
            $html .= '<td>' . $safe($row->customer_name) . '</td>';
            $html .= '<td>' . $safe($row->warehouse_name) . '</td>';
            $html .= '<td x:num="' . $total . '">' . $total . '</td>';
            $html .= '<td>' . $safe($row->user_name) . '</td>';
            $html .= '<td>' . $safe($status($row->status)) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function saleLookup(Request $request)
    {
        $data = $request->validate(['invoice_no' => 'required|string']);
        $entity = $this->entityId();

        $sale = DB::table('sales as s')
            ->leftJoin('customers as c', 'c.id', '=', 's.customer_id')
            ->where('s.entity_id', $entity)
            ->where('s.invoice_no', $data['invoice_no'])
            ->where('s.status', 'posted')
            ->select('s.*', DB::raw("COALESCE(c.name, 'Umum') as customer_name"))
            ->first();

        abort_unless($sale, 404, 'Nomor struk tidak ditemukan.');

        $items = DB::table('sale_items as si')
            ->join('products as p', 'p.id', '=', 'si.product_id')
            ->where('si.sale_id', $sale->id)
            ->select('si.id as sale_item_id', 'si.product_id', 'p.sku', 'p.name', 'si.qty', 'si.unit_price', 'si.discount', 'si.total')
            ->orderBy('si.id')
            ->get();

        foreach ($items as $item) {
            $returned = (float) DB::table('sales_return_items as ri')
                ->join('sales_returns as rr', 'rr.id', '=', 'ri.sales_return_id')
                ->where('rr.sale_id', $sale->id)
                ->where('ri.sale_item_id', $item->sale_item_id)
                ->sum('ri.qty');

            $item->returned_qty = $returned;
            $item->available_qty = max(0, (float) $item->qty - $returned);
            $item->hpp_unit = $this->saleHppUnit($sale->id, $item->product_id);
        }

        $payment = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->first(['method', 'amount']);
        $entityData = DB::table('entities')->where('id', $entity)->first(['name', 'address', 'phone']);

        return response()->json([
            'sale' => $sale,
            'items' => $items,
            'payment' => $payment,
            'entity' => $entityData,
        ]);
    }

    private function saleHppUnit(int $saleId, int $productId): float
    {
        $movement = DB::table('stock_movements')
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->where('product_id', $productId)
            ->selectRaw('SUM(ABS(qty)) qty, SUM(ABS(qty) * unit_cost) cost')
            ->first();

        if ($movement && (float) $movement->qty > 0) {
            return (float) $movement->cost / (float) $movement->qty;
        }

        $saleHpp = DB::table('sale_items')
            ->where('sale_id', $saleId)
            ->where('product_id', $productId)
            ->whereNotNull('hpp_unit')
            ->selectRaw('
                SUM(COALESCE(base_qty, qty) * hpp_unit) / NULLIF(SUM(COALESCE(base_qty, qty)), 0) AS hpp_unit
            ')
            ->value('hpp_unit');

        return (float) ($saleHpp ?? 0);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => 'required|integer',
            'warehouse_id' => 'required|integer',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|integer',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.condition' => 'required|in:good,reject',
        ]);

        $entity = $this->entityId();

        $returnId = DB::transaction(function () use ($data, $entity) {
            $sale = DB::table('sales')->where('entity_id', $entity)->where('id', $data['sale_id'])->lockForUpdate()->first();
            abort_unless($sale, 404, 'Penjualan tidak ditemukan.');

            abort_unless(
                $sale->business_unit_id,
                422,
                'Penjualan tidak memiliki Business Unit.'
            );

            abort_unless(
                $sale->status === 'posted',
                422,
                'Penjualan belum berstatus posted dan tidak dapat diretur.'
            );

            $warehouse = DB::table('warehouses')->where('entity_id', $entity)->where('id', $data['warehouse_id'])->where('is_active', 1)->first();
            abort_unless($warehouse, 404, 'Gudang tidak ditemukan.');

            abort_unless(
                (int) $warehouse->business_unit_id === (int) $sale->business_unit_id,
                422,
                'Gudang tidak sesuai dengan Business Unit Penjualan.'
            );

            $saleItems = DB::table('sale_items')->where('sale_id', $sale->id)->get()->keyBy('id');
            $subtotal = (float) $sale->subtotal;
            $saleDiscount = (float) $sale->discount;
            $returnTotal = 0;
            $prepared = [];

            foreach ($data['items'] as $input) {
                $item = $saleItems->get((int) $input['sale_item_id']);
                abort_unless($item, 422, 'Detail penjualan tidak valid.');

                $returned = (float) DB::table('sales_return_items as ri')
                    ->join('sales_returns as rr', 'rr.id', '=', 'ri.sales_return_id')
                    ->where('rr.sale_id', $sale->id)
                    ->where('ri.sale_item_id', $item->id)
                    ->sum('ri.qty');

                $qty = (float) $input['qty'];
                abort_if($qty > max(0, (float) $item->qty - $returned), 422, 'Qty retur melebihi qty yang masih dapat diretur.');
                $conversionFactor = (float) ($item->conversion_factor ?: 1);
                $baseQty = round($qty * $conversionFactor, 9);

                $grossItem = (float) $item->total;
                $allocatedDiscount = $subtotal > 0 ? ($grossItem / $subtotal) * $saleDiscount : 0;
                $netItem = max(0, $grossItem - $allocatedDiscount);
                $netUnitPrice = (float) $item->qty > 0 ? $netItem / (float) $item->qty : 0;
                $returnValue = round($netUnitPrice * $qty, 2);
                $hppUnit = (float) ($item->hpp_unit ?: $this->saleHppUnit($sale->id, (int) $item->product_id));
                $hppTotal = round($hppUnit * $baseQty, 2);

                $prepared[] = compact('item', 'qty', 'baseQty', 'conversionFactor', 'returnValue', 'hppUnit', 'hppTotal') + ['condition' => $input['condition']];
                $returnTotal += $returnValue;
            }

            abort_if($returnTotal <= 0, 422, 'Nilai retur harus lebih besar dari nol.');

            $businessUnit = DB::table('business_units')
                ->where('id', $sale->business_unit_id)
                ->where('entity_id', $entity)
                ->first(['id', 'business_type']);

            abort_unless($businessUnit, 422, 'Business Unit penjualan tidak ditemukan.');

            $prefix = match ($businessUnit->business_type) {
                'retail' => 'RET',
                'production' => 'PRO',
                'service' => 'JAS',
                default => throw new RuntimeException('Jenis Business Unit tidak valid untuk nomor retur.'),
            };

            $monthKey = now()->format('Ym');

            DB::table('entities')
                ->where('id', $entity)
                ->lockForUpdate()
                ->first();

            $lastReturn = DB::table('sales_returns')
                ->where('entity_id', $entity)
                ->where('return_no', 'like', $prefix . '-' . $monthKey . '-%')
                ->orderByDesc('id')
                ->value('return_no');

            $sequence = 1;
            if ($lastReturn && preg_match('/^' . preg_quote($prefix, '/') . '-' . $monthKey . '-([0-9]{6})$/', $lastReturn, $matches)) {
                $sequence = ((int) $matches[1]) + 1;
            }

            abort_if($sequence > 999999, 422, 'Nomor retur bulan ini sudah mencapai batas 999999.');

            $returnNo = $prefix . '-' . $monthKey . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
            $returnId = DB::table('sales_returns')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $sale->business_unit_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'warehouse_id' => $warehouse->id,
                'user_id' => auth()->id(),
                'return_no' => $returnNo,
                'return_date' => now(),
                'total' => $returnTotal,
                'reason' => $data['reason'] ?? null,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($prepared as $p) {
                $item = $p['item'];
                DB::table('sales_return_items')->insert([
                    'sales_return_id' => $returnId,
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'qty' => $p['qty'],
                    'conversion_factor' => $p['conversionFactor'],
                    'base_qty' => $p['baseQty'],
                    'unit_price' => $p['returnValue'] / $p['qty'],
                    'return_value' => $p['returnValue'],
                    'hpp_unit' => $p['hppUnit'],
                    'hpp_total' => $p['hppTotal'],
                    'condition' => $p['condition'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $stock = DB::table('warehouses_stocks')
                    ->where('entity_id', $entity)
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->lockForUpdate()
                    ->first();

                if ($p['condition'] === 'good') {
                    if ($stock) {
                        $oldQty = (float) $stock->qty;
                        $oldCost = (float) $stock->avg_cost;
                        $newQty = $oldQty + $p['baseQty'];
                        $newAvg = $newQty > 0 ? (($oldQty * $oldCost) + ($p['baseQty'] * $p['hppUnit'])) / $newQty : $p['hppUnit'];
                        DB::table('warehouses_stocks')->where('id', $stock->id)->update(['qty' => $newQty, 'avg_cost' => $newAvg, 'updated_at' => now()]);
                    } else {
                        DB::table('warehouses_stocks')->insert([
                            'entity_id' => $entity,
                            'warehouse_id' => $warehouse->id,
                            'product_id' => $item->product_id,
                            'qty' => $p['baseQty'],
                            'avg_cost' => $p['hppUnit'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                DB::table('stock_movements')->insert([
                    'entity_id' => $entity,
                    'business_unit_id' => $sale->business_unit_id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'unit_id' => $item->unit_id,
                    'transaction_qty' => $p['qty'],
                    'conversion_factor' => $p['conversionFactor'],
                    'movement_type' => $p['condition'] === 'good' ? 'sale_return_in' : 'sale_return_reject',
                    'qty' => $p['baseQty'],
                    'unit_cost' => $p['hppUnit'],
                    'reference_type' => 'sales_return',
                    'reference_id' => $returnId,
                    'occurred_at' => now(),
                    'created_by' => auth()->id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $businessUnit = DB::table('business_units')
                ->where('id', $sale->business_unit_id)
                ->where('entity_id', $entity)
                ->first(['id', 'business_type']);

            abort_unless($businessUnit, 422, 'Business Unit penjualan tidak ditemukan.');

            $payment = DB::table('payments')->where('sale_id', $sale->id)->orderBy('id')->first(['method', 'amount']);
            $paid = (float) DB::table('payments')->where('sale_id', $sale->id)->sum('amount');

            $refundAccount = $paid >= (float) $sale->total
                ? $this->mappingAccountId(
                    $entity,
                    (int) $sale->business_unit_id,
                    $payment && in_array(strtolower($payment->method), ['transfer', 'qris'], true)
                        ? 'bank'
                        : 'cash'
                )
                : $this->mappingAccountId(
                    $entity,
                    (int) $sale->business_unit_id,
                    'receivable'
                );

            $returnMappingKey = match ($businessUnit->business_type) {
                'retail' => 'sales_return_merchandise',
                'production' => 'sales_return_finished_goods',
                default => null,
            };

            abort_unless(
                $returnMappingKey,
                422,
                'Business Unit ini tidak mendukung Retur Penjualan.'
            );

            $returnAccount = $this->mappingAccountId(
                $entity,
                (int) $sale->business_unit_id,
                $returnMappingKey
            );

            $inventoryAccount = $this->mappingAccountId(
                $entity,
                (int) $sale->business_unit_id,
                'inventory'
            );

            $cogsMappingKey = match ($businessUnit->business_type) {
                'retail' => 'cogs_merchandise',
                'production' => 'cogs_finished_goods',
                default => null,
            };

            abort_unless(
                $cogsMappingKey,
                422,
                'Mapping HPP Business Unit tidak ditemukan.'
            );

            $cogsAccount = $this->mappingAccountId(
                $entity,
                (int) $sale->business_unit_id,
                $cogsMappingKey
            );

            $damageAccount = $this->mappingAccountId(
                $entity,
                (int) $sale->business_unit_id,
                'inventory_damage_loss'
            );

            $customerName = $sale->customer_id
                ? DB::table('customers')
                    ->where('entity_id', $entity)
                    ->where('id', $sale->customer_id)
                    ->value('name')
                : null;

            $description = 'Retur Penjualan No. ' . $returnNo
                . ' (Ref: ' . $sale->invoice_no . ')'
                . ($customerName ? ' - ' . $customerName : '');
            $journalNo = 'JRN-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(3));
            $journalId = DB::table('journals')->insertGetId([
                'entity_id' => $entity,
                'business_unit_id' => $sale->business_unit_id,
                'journal_no' => $journalNo,
                'journal_date' => today(),
                'source_type' => 'sales_return',
                'source_id' => $returnId,
                'description' => $description,
                'status' => 'posted',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $totalGoodHpp = array_sum(
                array_map(
                    fn ($p) => $p['condition'] === 'good' ? $p['hppTotal'] : 0,
                    $prepared
                )
            );

            $totalRejectHpp = array_sum(
                array_map(
                    fn ($p) => $p['condition'] === 'reject' ? $p['hppTotal'] : 0,
                    $prepared
                )
            );

            $journalEntries = [
                [
                    'journal_id' => $journalId,
                    'account_id' => $returnAccount,
                    'debit' => $returnTotal,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'journal_id' => $journalId,
                    'account_id' => $refundAccount,
                    'debit' => 0,
                    'credit' => $returnTotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ];

            if ($totalGoodHpp > 0) {
                $journalEntries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $inventoryAccount,
                    'debit' => $totalGoodHpp,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $journalEntries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $cogsAccount,
                    'debit' => 0,
                    'credit' => $totalGoodHpp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($totalRejectHpp > 0) {
                $journalEntries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $damageAccount,
                    'debit' => $totalRejectHpp,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $journalEntries[] = [
                    'journal_id' => $journalId,
                    'account_id' => $inventoryAccount,
                    'debit' => 0,
                    'credit' => $totalRejectHpp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('journal_entries')->insert($journalEntries);

            return $returnId;
        });

        return redirect()->route('inventori.penjualan.retur')->with('success', 'Retur berhasil diproses dan jurnal otomatis dibuat.');
    }
}
