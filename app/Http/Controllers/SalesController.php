<?php
namespace AppHttpControllers;
use IlluminateHttpRequest;
use IlluminateSupportFacadesDB;
class SalesController extends Controller {
 private function entityId(): int { return (int)(DB::table('entities')->value('id') ?? 1); }
 public function index(Request $request) {
  $entity=$this->entityId();
  $rows=DB::table('sales as s')->leftJoin('customers as c','c.id','=','s.customer_id')->leftJoin('business_units as bu','bu.id','=','s.unit_id')->where('s.entity_id',$entity)->when($request->filled('start_date'),fn($q)=>$q->whereDate('s.sale_date','>=',$request->start_date))->when($request->filled('end_date'),fn($q)=>$q->whereDate('s.sale_date','<=',$request->end_date))->when($request->filled('customer'),fn($q)=>$q->where('c.name','like','%'.$request->customer.'%'))->when($request->filled('unit_id'),fn($q)=>$q->where('s.unit_id',$request->unit_id))->select('s.*','c.name as customer_name','bu.name as unit_name')->orderByDesc('s.id')->paginate(15)->withQueryString();
  $units=DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
  return view('erp.sales.index',compact('rows','units'));
 }
 public function create() {
  $entity=$this->entityId();
  $units=DB::table('business_units')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get();
  $customers=DB::table('customers')->where('entity_id',$entity)->where('is_active',1)->orderBy('name')->get(['id','name']);
  $products=DB::table('products as p')->join('product_units as pu','pu.product_id','=','p.id')->leftJoin('units as u','u.id','=','p.base_unit_id')->where('p.entity_id',$entity)->where('p.is_active',1)->select('p.id','p.code','p.sku','p.name','p.selling_price','u.code as selling_unit_code')->distinct()->orderBy('p.name')->get();
  return view('erp.sales.create',compact('units','customers','products'));
 }
}