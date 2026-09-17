<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $products = Product::count();
        $customers = Customer::count();
        $suppliers = Supplier::count();
        $vehicles = Vehicle::count();

        return view('dashboard.index', compact(
            'products',
            'customers',
            'suppliers',
            'vehicles'
        ));
    }
}
