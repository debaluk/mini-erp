<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MasterController;
Route::get('/', fn()=>redirect('/dashboard'));
Route::get('/dashboard', [DashboardController::class,'index'])->name('dashboard');
Route::prefix('master')->group(function(){
 Route::get('/', [MasterController::class,'index'])->name('master.index');
});
