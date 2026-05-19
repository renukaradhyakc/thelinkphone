<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Modules\Loyalty\Models\Bill;
use App\Modules\Loyalty\Models\LoyaltyPoint;
use App\Modules\Loyalty\Services\Admin\BillOverrideService;

class AdminBillController extends Controller
{
    public function __construct(
        protected BillOverrideService $overrideService
    ) {}

    public function approve($id, Request $request)
    {
        $bill = Bill::findOrFail($id);

        $this->overrideService->approve(
            $bill,
            auth()->id(), // replace your hardcoded 1
            $request->note
        );

        return response()->json(['message' => 'Bill approved']);
    }

    public function reject($id, Request $request)
    {
        $bill = Bill::findOrFail($id);

        $this->overrideService->reject(
            $bill,
            auth()->user()->id,
            $request->note
        );

        return response()->json(['message' => 'Bill rejected']);
    }
}