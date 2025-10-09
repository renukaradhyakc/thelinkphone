<?php

namespace App\Http\Controllers;

use App\Repositories\CashPaymentRepository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laracasts\Flash\Flash;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CashPaymentController extends AppBaseController
{
    /** @var CashPaymentRepository */
    private $cashPaymentRepo;

    public function __construct(CashPaymentRepository $cashPaymentRepository)
    {
        $this->cashPaymentRepo = $cashPaymentRepository;
    }

    /**
     * @return Application|Factory|View
     */
    public function index(): \Illuminate\View\View
    {
        return view('cash_payments.index');
    }

    public function cashPay(Request $request): JsonResponse
    {
        $input = $request->all();
        $subscription = $this->cashPaymentRepo->cashPayData($input);

        Flash::success($subscription->subscriptionPlan->name.' '.__('messages.subscription_pricing_plans.has_been_subscribed'));

        return response()->json([
            'toastType' => 'success',
            'url' => route('subscription.pricing.plans.index'),
        ]);
    }

    /**
     * @return mixed
     */
    public function downloadAttachment($mediaId)
    {
        $mediaItem = Media::find($mediaId);

        return $mediaItem;
    }
}
