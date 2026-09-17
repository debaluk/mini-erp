<?php
namespace App\Http\Controllers;
use App\Models\Product; use App\Models\Customer; use App\Models\Supplier; use App\Models\Vehicle;
class DashboardController extends Controller { public function index(){ return view('dashboard.index', compact('products','customers','suppliers','vehicles') + ['products'=>Product::count(),'customers'=>Customer::count(),'suppliers'=>Supplier::count(),'vehicles'=>Vehicle::count()]); } }
