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

        return response()->json([
            'data' => $this->engine->report(
                $entityId,
                $request->integer('business_unit_id') ?: null,
                $request->input('start_date'),
                $request->input('end_date')
            ),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
            'account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'driver_id' => ['nullable', 'integer', 'exists:drivers,id'],
            'cost_date' => ['nullable', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'source' => ['nullable', 'string', 'max:50'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'integer'],
        ]);

        $sale = DB::table('sales')
            ->where('id', $data['sale_id'])
            ->where('entity_id', auth()->user()->entity_id)
            ->first();

        abort_unless($sale, 404);

        $data['entity_id'] = $sale->entity_id;
        $data['business_unit_id'] = $sale->business_unit_id;

        $cost = $this->engine->addCost((int) $sale->id, $data);

        return response()->json([
            'message' => 'Beban Langsung berhasil dicatat.',
            'data' => $cost,
            'summary' => $this->engine->summary((int) $sale->id),
        ], 201);
    }

    public function show(int $saleId)
    {
        $sale = DB::table('sales')
            ->where('id', $saleId)
            ->where('entity_id', auth()->user()->entity_id)
            ->first();

        abort_unless($sale, 404);

        return response()->json($this->engine->summary($saleId));
    }
}
