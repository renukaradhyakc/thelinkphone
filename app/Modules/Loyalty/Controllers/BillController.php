<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\BillItem;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Jobs\ProcessBillJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BillController extends Controller
{
    public function store(Request $request)
    {
        $start = microtime(true);
        Log::info('STORE_START');
        \Log::info('Bill upload request', [
            'has_file' => $request->hasFile('bill'),
            'file_valid' => $request->hasFile('bill') ? $request->file('bill')->isValid() : false,
            'mime' => $request->hasFile('bill') ? $request->file('bill')->getMimeType() : null,
            'size' => $request->hasFile('bill') ? $request->file('bill')->getSize() : null,
        ]);

        $request->validate([
            'bill' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        Log::info('AFTER_VALIDATION', [
            'elapsed_ms' => round((microtime(true) - $start) * 1000)
        ]);

        $file = $request->file('bill');
        $userId = auth()->user()->id;

        // ✅ Generate hash (duplicate prevention)
        $hash = hash_file('sha256', $file->getRealPath());

        Log::info('AFTER_HASH', [
            'elapsed_ms' => round((microtime(true) - $start) * 1000)
        ]);

        if (Bill::where('hash', $hash)->exists()) {
            return $this->duplicateResponse($request);
        }

        try {
            // ✅ Store file
            $path = $file->store('bills', 'private');

            Log::info('AFTER_FILE_STORE', [
                'elapsed_ms' => round((microtime(true) - $start) * 1000)
            ]);

            $bill = Bill::create([
                'user_id' => $userId,
                'file_url' => $path,
                'status' => Bill::STATUS_PENDING,
                'hash' => $hash,
            ]);

            Log::info('AFTER_BILL_CREATE', [
                'elapsed_ms' => round((microtime(true) - $start) * 1000)
            ]);
        } catch (\Illuminate\Database\QueryException $e) {

            \Log::error('Bill insert failed', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // MySQL duplicate entry error
            if ($e->getCode() == 23000) {
                return $this->duplicateResponse($request);
            }

            return $this->uploadFailedResponse($request);
        } catch (\Exception $e) {

            \Log::error('Bill upload failed', [
                'message' => $e->getMessage(),
            ]);
            return $this->uploadFailedResponse($request);
        }
        
        Log::info('QUEUE_DRIVER', [
            'driver' => config('queue.default')
        ]);

        // ✅ Dispatch job
        ProcessBillJob::dispatch($bill->id);

        Log::info('AFTER_JOB_DISPATCH', [
            'elapsed_ms' => round((microtime(true) - $start) * 1000)
        ]);

        Log::info('STORE_COMPLETE', [
            'elapsed_ms' => round((microtime(true) - $start) * 1000)
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'bill_id' => $bill->id,
                    'message' => 'Uploaded'
                ]
            ]);
        }

        return redirect()
        ->back()
        ->with('success', 'Bill uploaded successfully');
    }

    public function show(Request $request, $id)
    {
        $bill = Bill::where('id', $id)
                ->where('user_id', auth()->id())
                ->with(['items' => function($q){ $q->orderBy('position'); }, 'loyaltyPoint'])
                ->firstOrFail();

        if (!$request->ajax() && !$request->wantsJson()) {
            return view('loyalty.bill.show', ['billId' => $bill->id]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bill_id' => $bill->id,
                'status' => $bill->status,
                'vendor' => $bill->vendor_name,
                'amount' => $bill->amount,
                'bill_date' => $bill->bill_date ? $bill->bill_date->format('Y-m-d') : null,
                'invoice_number' => $bill->invoice_number,
                'confidence' => $bill->confidence,
                'provider'       => $bill->provider,
                'processed_at'   => $bill->processed_at?->format('d M Y, h:i A'),
                'points'         => $bill->loyaltyPoint?->points ?? 0,
                'items'          => $bill->items->map(fn($item) => [
                    'description'   => $item->description,
                    'category'      => $item->category,
                    'hsn_code'      => $item->hsn_code,
                    'quantity'      => $item->quantity,
                    'unit_price'    => $item->unit_price,
                    'line_total'    => $item->line_total,
                    'is_eligible'   => $item->is_eligible,
                    'points_earned' => $item->points_earned,
                ]),
            ]
        ]);
    }

    public function points()
    {
        $userId = auth()->user()->id;

        $total = LoyaltyPoint::where('user_id', $userId)->sum('points');

        return response()->json([
            'success' => true,
            'data' => [
                'total_points' => $total
            ]
        ]);
    }

    public function history(Request $request)
    {
        $userId = auth()->user()->id;

        $query = Bill::with('loyaltyPoint')->where('user_id', $userId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $bills = $query
            ->latest()
            ->paginate(10)
            ->through(function ($bill) {
                return [
                    'bill_id' => $bill->id,
                    'status' => $bill->status,
                    'vendor' => $bill->vendor_name,
                    'amount' => (float) $bill->amount,
                    'bill_date' => $bill->bill_date,
                    'provider' => $bill->provider,
                    'confidence' => $bill->confidence,
                    'points' => $bill->loyaltyPoint->points ?? 0,
                    'created_at' => $bill->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $bills->items(),
            'meta' => [
                'current_page' => $bills->currentPage(),
                'per_page' => $bills->perPage(),
                'total' => $bills->total(),
                'last_page' => $bills->lastPage(),
                'has_more' => $bills->hasMorePages(),
            ]
        ]);
    }

    public function download($id)
    {
        $bill = Bill::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        $disk = Storage::disk('private');

        if (!$disk->exists($bill->file_url)) {
            abort(404);
        }

        return $disk->response($bill->file_url);
    }

    public function items(Request $request, $id)
    {
        $perPage = (int) $request->get('per_page', 10);

        $bill = Bill::where('id', $id)
            ->where('user_id', auth()->id()) 
            ->firstOrFail();

        $items = $bill->items()
            ->select([
                'id',
                'bill_id',
                'description',
                'brand',
                'category',
                'categories',
                'item_type',
                'quantity',
                'unit_price',
                'line_total',
                'computed_total',
                'discount',
                'is_eligible',
                'points_earned',
                'position',
            ])
            ->orderBy('position')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'bill_id' => $bill->id,
                'items' => $items->items(),
                'pagination' => [
                    'current_page' => $items->currentPage(),
                    'last_page'    => $items->lastPage(),
                    'per_page'     => $items->perPage(),
                    'total'        => $items->total(),
                ],
            ]
        ]);
    }

    private function duplicateResponse(Request $request)
    {
        if ($request->expectsJson()) {

            return response()->json([
                'success' => false,
                'error' => 'Duplicate bill'
            ], 409);
        }

        return redirect()
            ->back()
            ->withErrors([
                'bill' => 'Duplicate bill already uploaded'
            ]);
    }

    private function uploadFailedResponse(Request $request)
    {
        if ($request->expectsJson()) {

            return response()->json([
                'success' => false,
                'error' => 'Failed to upload bill'
            ], 500);
        }

        return redirect()
            ->back()
            ->withErrors([
                'bill' => 'Failed to upload bill'
            ]);
    }

    public function status($id)
    {
        $bill = Bill::where('id', $id)
            ->where('user_id', auth()->id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'bill_id' => $bill->id,
                'status' => $bill->status,
            ]
        ]);
    }
}