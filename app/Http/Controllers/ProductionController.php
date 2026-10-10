<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductionController extends Controller
{
    private const COST_GROUPS = [
        'B' => 'Bahan',
        'U' => 'Upah',
        'A' => 'Alat',
        'S' => 'Sewa',
        'O' => 'Overhead',
    ];

    private function entityId(): int
    {
        return (int) (auth()->user()?->entity_id ?? 0);
    }

    public function index()
    {
        return redirect()->route('produksi.work-order');
    }

    public function store(Request $request)
    {
        return redirect()->route('produksi.work-order')
            ->with('error', 'Posting produksi langsung sudah tidak digunakan. Silakan gunakan alur SPK.');
    }
}
