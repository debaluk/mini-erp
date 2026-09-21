<?php

namespace App\Http\Controllers;

use App\Services\Production\ProductionCostEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProductionController extends Controller
{
    public function __construct(
        protected ProductionCostEngine $costEngine,
    ) {}

    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function store(Request $request)
    {
        $entityId = $this->entityId();
        $businessUnitId = (int) ($request->input('business_unit_id') ?? $request->input('unit_id'));

        $data = $request->validate([
            'bom_id' => ['required','integer'],
            'warehouse_id' => ['required','integer'],
            'qty' => ['required','numeric','gt:0'],
            'business_unit_id' => ['nullable','integer'],
            'unit_id' => ['nullable','integer'],
            'costs' => ['nullable','array'],
            'costs.*.cost_group' => ['nullable','string'],
            'costs.*.description' => ['nullable','string'],
            'costs.*.amount' => ['nullable','numeric','min:0'],
            'rejects' => ['nullable','array'],
            'rejects.*.qty' => ['nullable','numeric','min:0'],
            'rejects.*.reject_type' => ['nullable','string'],
            'rejects.*.recoverable_value' => ['nullable','numeric','min:0'],
            'rejects.*.product_id' => ['nullable','integer'],
            'rejects.*.description' => ['nullable','string'],
        ]);

        abort_if($businessUnitId <= 0, 422, 'Unit Bisnis wajib dipilih.');

        try {
            $result = $this->costEngine->post(
                entityId: $entityId,
                businessUnitId: $businessUnitId,
                warehouseId: (int) $data['warehouse_id'],
                bomId: (int) $data['bom_id'],
                qty: (float) $data['qty'],
                userId: (int) auth()->id(),
                costs: $data['costs'] ?? [],
                rejects: $data['rejects'] ?? [],
            );
        } catch (Throwable $e) {
            abort(422, $e->getMessage());
        }

        return back()->with('success',
            'Produksi '.$result['production_no'].' berhasil. '.
            'HPP Rp '.number_format($result['total_cost'], 2, ',', '.').
            ' / '.$result['good_output_qty'].' unit = Rp '.
            number_format($result['unit_cost'], 4, ',', '.').' per unit.'
        );
    }
}
