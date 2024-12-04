<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function getAll(Request $request)
    {
        $invoices = Invoice::query()->with('client', 'items');

        if ($request->has('sort')) {
            $method = $request['sort'];

            if ($method == 'invoice_number') {
                $invoices->orderBy('invoice_number');
            } else if ($method == 'due_date') {
                $invoices->orderBy('due_date');
            } else if ($method == 'total_amount') {
                $invoices->orderBy('total_amount');
            }
        }

        if ($request->has('search_query')) {
            $invoices->where('invoice_number', 'like', '%' . $request['search_query'] . '%')
                ->orWhereHas('client', function ($query) use ($request) {
                    $query->where('name', 'like', '%' . $request['search_query'] . '%');
                });
        }

        return response()->json([
            'invoices' => InvoiceResource::collection($invoices->paginate()),
        ]);
    }

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

                return $this->attachItems($invoice, $validatedData['items']);
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

    public function update(Request $request, Invoice $invoice)
    {
        try {
            $validatedData = $request->validate([
                'invoice_number' => 'required',
                'client_id' => 'required|exists:clients,id',
                'items' => 'required|array|min:1',
                'items.*.id' => 'required|exists:items,id',
                'items.*.quantity' => 'required|integer|min:1',
                'items.*.amount' => 'required|numeric|min:0',
                'due_date' => 'required|date:after_or_equal:today',
            ]);

            $invoice = DB::transaction(function () use ($validatedData, $invoice) {

                $invoice->items()->detach();

                $invoice = $this->attachItems($invoice, $validatedData['items']);

                $invoice->update([
                    'invoice_number' => $validatedData['invoice_number'],
                    'client_id' => $validatedData['client_id'],
                    'due_date' => $validatedData['due_date'],
                ]);

                return $invoice;
            });

            return response()->json([
                'invoice' => new InvoiceResource($invoice),
            ]);

        } catch (\Exception $e) {

            return response()->json([
                'message' => 'Failed to update invoice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function delete(Invoice $invoice)
    {
        $invoice->items()->detach();

        $invoice->delete();

        return response()->json([
            'message' => 'Invoice deleted successfully',
        ]);
    }

    private function attachItems(Invoice $invoice, $items)
    {
        $totalAmount = 0;

        foreach ($items as $item) {

            $totalAmount += $item['quantity'] * $item['amount'];

            $invoice->items()->attach($item['id'], [
                'quantity' => $item['quantity'],
                'amount' => $item['amount']
            ]);
        }

        $invoice->update(['total_amount' => $totalAmount]);

        return $invoice;
    }
}
