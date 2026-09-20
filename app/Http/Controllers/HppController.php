<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HppController extends Controller
{
    private function entityId(): int
    {
        return (int) (DB::table('entities')->value('id') ?? 0);
    }

    public function index(Request $request)
    {
        $entityId = $this->entityId();
        $entity = DB::table('entities')->where('id', $entityId)->first();

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        return view('erp.hpp.index', [
            'entity' => $entity,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'retailRows' => collect(),
            'productionRows' => collect(),
        ]);
    }
}
