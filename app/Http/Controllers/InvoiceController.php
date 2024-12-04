<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'invoice_number' => 'required|unique:invoices',
                'client_id' => 'required|exists:clients,id',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.amount' => 'required|numeric|min:0',
                'due_date' => 'required|date:after_or_equal:today',
            ]);

            $invoice = DB::transaction(function () use ($validatedData) {

                $invoice = Invoice::create([
                    'invoice_number' => $validatedData['invoice_number'],
                    'client_id' => $validatedData['client_id'],
                    'due_date' => $validatedData['due_date'],
                ]);

                $totalAmount = 0;

                foreach ($validatedData['items'] as $item) {

                    $totalAmount += $item['quantity'] * $item['amount'];

                    $invoice->items()->attach($item['id'], [
                        'quantity' => $item['quantity'],
                        'amount' => $item['amount']
                    ]);
                }

                $invoice->update(['total_amount' => $totalAmount]);

                return $invoice;
            });

            return response()->json([
                'invoice' => new InvoiceResource($invoice),
            ], 201);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to create invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
