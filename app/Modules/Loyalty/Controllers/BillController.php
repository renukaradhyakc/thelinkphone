<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\BillItem;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Jobs\ProcessBillJob;
use Illuminate\Support\Facades\Storage;

class BillController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'bill' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        $file = $request->file('bill');
        $userId = auth()->user()->id;

        // ✅ Generate hash (duplicate prevention)
        $hash = hash_file('sha256', $file->getRealPath());

        if (Bill::where('hash', $hash)->exists()) {
            return response()->json(['error' => 'Duplicate bill'], 409);
        }

        try {
            // ✅ Store file
            $path = $file->store('bills', 'private');

            $bill = Bill::create([
                'user_id' => $userId,
                'file_url' => $path,
                'status' => Bill::STATUS_PENDING,
                'hash' => $hash,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {

            \Log::error('Bill insert failed', [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            // MySQL duplicate entry error
            if ($e->getCode() == 23000) {
                return response()->json([
                    'error' => 'Duplicate bill'
                ], 409);
            }

            return response()->json([
                'error' => 'Failed to upload bill'
            ], 500);
        } catch (\Exception $e) {

            \Log::error('Bill upload failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to upload bill'
            ], 500);
        }

        // ✅ Dispatch job
        ProcessBillJob::dispatch($bill->id);

        return response()->json([
            'message' => 'Uploaded',
            'bill_id' => $bill->id
        ]);
    }

    public function show(Request $request, $id)
    {
        $bill = Bill::where('id', $id)
                ->where('user_id', auth()->id())
                ->firstOrFail();

        if (!$request->ajax() && !$request->wantsJson()) {
            return view('loyalty.bills.show', ['billId' => $bill->id]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'bill_id' => $bill->id,
                'status' => $bill->status,
                'vendor' => $bill->vendor_name,
                'amount' => $bill->amount,
                'bill_date' => $bill->bill_date,
                'confidence' => $bill->confidence,
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

        $bills = Bill::with('loyaltyPoint')
            ->where('user_id', $userId)
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
}