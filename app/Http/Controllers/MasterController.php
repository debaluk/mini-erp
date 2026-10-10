<?php

namespace App\\Http\\Controllers;

use App\\Models\\Product;
use App\\Models\\Customer;
use App\\Models\\Supplier;
use App\\Models\\Warehouse;
use App\\Models\\Unit;
use App\\Models\\Tariff;
use App\\Models\\Vehicle;
use App\\Models\\Driver;

class MasterController extends Controller
{
    public function index()
    {
        $entityId = (int) (auth()->user()?->entity_id ?? 0);
        abort_unless($entityId > 0, 403, 'Entitas pengguna tidak valid.');

        return view('master.index', [
            'products' => Product::where('entity_id', $entityId)->latest()->take(10)->get(),
            'customers' => Customer::where('entity_id', $entityId)->latest()->take(10)->get(),
            'suppliers' => Supplier::where('entity_id', $entityId)->latest()->take(10)->get(),
            'warehouses' => Warehouse::where('entity_id', $entityId)->latest()->take(10)->get(),
            'units' => Unit::where('entity_id', $entityId)->latest()->take(10)->get(),
            'tariffs' => Tariff::where('entity_id', $entityId)->latest()->take(10)->get(),
            'vehicles' => Vehicle::where('entity_id', $entityId)->latest()->take(10)->get(),
            'drivers' => Driver::where('entity_id', $entityId)->latest()->take(10)->get(),
        ]);
    }
}
