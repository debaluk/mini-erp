<?php

namespace App\Http\Controllers;

use App\Services\Service\ServiceCostEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceCostController extends Controller
{
    public function __construct(private ServiceCostEngine $engine)
    {
    }

    public function index(Request $request)
    {
        $entityId = (int) auth()->user()->entity_id;
        $businessUnitId = $request->integer('business_unit_id') ?: null;
        $start = $request->input('start_date');
        $end = $request->input('end_date');

        return response()->json([
            'data' => $this->engine->report($entityId, $businessUnitId, $start, $end),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'delivery_id' => ['required', 'integer', 'exists:deliveries,id'],
            'business_unit_id' => ['nullable', 'integer', 'exists:business_units,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'cost_date' => ['nullable', 'date'],
            'cost_type' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        $delivery = DB::table('deliveries')
            ->where('id', $data['delivery_id'])
            ->where('entity_id', auth()->user()->entity_id)
            ->first();

        abort_unless($delivery, 404);

        $data['entity_id'] = $delivery->entity_id;
        $data['business_unit_id'] = $delivery->business_unit_id;

        $cost = $this->engine->addCost((int) $delivery->id, $data);

        return response()->json([
            'message' => 'Direct cost jasa berhasil dicatat.',
            'data' => $cost,
            'summary' => $this->engine->summary((int) $delivery->id),
        ], 201);
    }

    public function revenue(Request $request, int $deliveryId)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gte:0'],
        ]);

        $delivery = DB::table('deliveries')
            ->where('id', $deliveryId)
            ->where('entity_id', auth()->user()->entity_id)
            ->first();

        abort_unless($delivery, 404);

        return response()->json([
            'message' => 'Pendapatan jasa berhasil diperbarui.',
            'data' => $this->engine->setRevenue($deliveryId, (float) $data['amount']),
        ]);
    }

    public function show(int $deliveryId)
    {
        $delivery = DB::table('deliveries')
            ->where('id', $deliveryId)
            ->where('entity_id', auth()->user()->entity_id)
            ->first();

        abort_unless($delivery, 404);

        return response()->json($this->engine->summary($deliveryId));
    }
}
