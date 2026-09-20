<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SalesController extends Controller {
 private function entityId(): int { return (int)(DB::table('entities')->value('id') ?? 1); }
 public function index(Request $request) {
  $entity=$this->entityId();
  $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
  $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();
  $rows=DB::table('sales as s')->leftJoin('customers as c','c.id','=','s.customer_id')->where('s.entity_id',$entity)->whereDate('s.sale_date','>=',$startDate)->whereDate('s.sale_date','<=',$endDate)->when($request->filled('customer'),fn($q)=>$q->where('c.name','like','%'.$request->customer.'%'))->when($request->filled('unit_id'),fn($q)=>$q->where('s.unit_id',$request->unit_id))->when($request->filled('payment_method'),fn($q)=>$q->whereExists(function($sub) use ($request){$sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id','s.id')->where('fp.method',$request->payment_method);}))->leftJoin('business_units as bu','bu.id','=','s.unit_id')->select('s.*','c.name as customer_name','bu.name as unit_name',DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"))->orderByDesc('s.sale_date')->orderByDesc('s.id')->paginate(10)->withQueryString();
  $units=DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
  return view('erp.sales.index',compact('rows','units'));
 }
 public function export(Request $request) {
  $entity=$this->entityId();
  $startDate = $request->filled('start_date') ? $request->input('start_date') : now()->startOfMonth()->toDateString();
  $endDate = $request->filled('end_date') ? $request->input('end_date') : now()->endOfMonth()->toDateString();
  $rows=DB::table('sales as s')
    ->leftJoin('customers as c','c.id','=','s.customer_id')
    ->where('s.entity_id',$entity)
    ->whereDate('s.sale_date','>=',$startDate)
    ->whereDate('s.sale_date','<=',$endDate)
    ->when($request->filled('customer'),fn($q)=>$q->where('c.name','like','%'.$request->customer.'%'))
    ->when($request->filled('unit_id'),fn($q)=>$q->where('s.unit_id',$request->unit_id))
    ->when($request->filled('payment_method'),fn($q)=>$q->whereExists(function($sub) use ($request){
      $sub->select(DB::raw(1))->from('payments as fp')->whereColumn('fp.sale_id','s.id')->where('fp.method',$request->payment_method);
    }))
    ->leftJoin('business_units as bu','bu.id','=','s.unit_id')->select('s.invoice_no','s.sale_date',DB::raw("COALESCE(c.name, 'Umum') as customer_name"),DB::raw("COALESCE(bu.name, '-') as unit_name"),DB::raw("(SELECT GROUP_CONCAT(DISTINCT p.method ORDER BY p.id SEPARATOR ', ') FROM payments p WHERE p.sale_id = s.id) as payment_methods"),DB::raw("'-' as due_date"),'s.subtotal','s.discount','s.total','s.status')
    ->orderByDesc('s.sale_date')->orderByDesc('s.id')->get();
  return response()->json([
    'entity_name'=>DB::table('entities')->where('id',$entity)->value('name') ?? 'NAMA ENTITAS',
    'start_date'=>$startDate,
    'end_date'=>$endDate,
    'rows'=>$rows
  ]);
 }
 public function show(int $id) {
  $entity=$this->entityId();
  $sale=DB::table('sales as s')->leftJoin('customers as c','c.id','=','s.customer_id')->where('s.entity_id',$entity)->where('s.id',$id)->leftJoin('business_units as bu','bu.id','=','s.unit_id')->select('s.*','c.name as customer_name','c.phone as customer_phone','c.address as customer_address','bu.name as unit_name')->first();
  abort_unless($sale,404);
  $items=DB::table('sale_items as si')->join('products as p','p.id','=','si.product_id')->leftJoin('units as u','u.id','=','p.base_unit_id')->where('si.sale_id',$sale->id)->select('p.code','p.name','u.code as unit_code','si.qty','si.unit_price','si.discount','si.total')->get();
  $payments=DB::table('payments')->where('sale_id',$sale->id)->orderBy('id')->get();
  return view('erp.sales.show',compact('sale','items','payments'));
 }
 public function print(int $id) { return $this->show($id); }
 public function create() {
  $entity=$this->entityId();
  $units=DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
  $customers=DB::table('customers')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get(['id','name']);
  $products=DB::table('products as p')->join('product_units as pu','pu.product_id','=','p.id')->leftJoin('units as u','u.id','=','p.base_unit_id')->where('p.entity_id',$entity)->where('p.is_active',1)->select('p.id','p.code','p.sku','p.name','p.selling_price','u.code as selling_unit_code')->distinct()->orderBy('p.name')->get();
  return view('erp.sales.create',compact('units','customers','products'));
 }
}