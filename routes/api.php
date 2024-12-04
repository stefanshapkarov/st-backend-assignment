<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//Route::get('/user', function (Request $request) {
//    return $request->user();
//})->middleware('auth:sanctum');

Route::get('/invoices', [InvoiceController::class, 'getAll']);
Route::post('/invoices/create', [InvoiceController::class, 'create']);
Route::patch('/invoices/{invoice}/update', [InvoiceController::class, 'update']);
