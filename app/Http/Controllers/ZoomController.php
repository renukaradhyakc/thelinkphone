<?php

namespace App\Http\Controllers;

use App\Models\ZoomIntegration;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laracasts\Flash\Flash;

class ZoomController extends AppBaseController
{
    private const AUTHORIZE_URL = 'https://zoom.us/oauth/authorize';
    private const TOKEN_URL = 'https://zoom.us/oauth/token';

    /**
     * Redirect the user to Zoom's OAuth consent screen.
     */
    public function oauth(): RedirectResponse
    {
        $clientId = config('services.zoom.client_id');
        $redirectUri = config('services.zoom.redirect_uri');

        if (empty($clientId) || empty($redirectUri)) {
            Flash::error('Zoom client credentials are not configured.');
            return redirect()->back();
        }

        $authUrl = self::AUTHORIZE_URL.'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
        ]);

        return redirect($authUrl);
    }

    /**
     * Handle Zoom's OAuth callback, exchange the code for tokens, and store them.
     */
    public function redirect(Request $request): RedirectResponse
    {
        if ($request->get('error')) {
            $returnUrl = session()->pull('pending_booking_return_url', route('events.index'));
            session()->forget('pending_booking');
            Flash::error('Booking requires Zoom access to continue.');
            return redirect($returnUrl);
        }

        $code = $request->get('code');

        if (empty($code)) {
            Flash::error('Zoom authorization failed — no code received.');
            return redirect(route('zoom.index'));
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth(config('services.zoom.client_id'), config('services.zoom.client_secret'))
                ->post(self::TOKEN_URL, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => config('services.zoom.redirect_uri'),
                ]);

            if ($response->failed()) {
                Log::error('[Zoom] token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                Flash::error('Could not connect your Zoom account. Please try again.');
                return redirect(route('zoom.index'));
            }

            $tokenData = $response->json();
            $meta = array_merge($tokenData, ['obtained_at' => now()->timestamp]);

            ZoomIntegration::updateOrCreate(
                ['user_id' => getLogInUserId()],
                [
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'meta' => json_encode($meta),
                    'last_used_at' => Carbon::now(),
                ]
            );

            if (session()->has('pending_booking')) {
                $pendingBooking = session()->pull('pending_booking');
                $returnUrl = session()->pull('pending_booking_return_url', route('events.index'));

                // Same slot/plan re-checks as the Google flow — mirror ScheduleEventController::store()'s
                // pre-booking validation here once the booking-form radio buttons (step 6) are wired in.

                $scheduleEventRepo = app(\App\Repositories\ScheduleEventRepository::class);
                $eventSchedule = $scheduleEventRepo->store($pendingBooking);

                Flash::success('Zoom connected and your meeting is booked.');
                return redirect(url(getSlotConfirmPageUrl($eventSchedule)));
            }

            Flash::success('Zoom account connected successfully.');
            return redirect(route('zoom.index'));
        } catch (\Exception $exception) {
            Log::error('[Zoom] OAuth callback error', ['error' => $exception->getMessage()]);
            Flash::error('Something went wrong connecting your Zoom account.');
            return redirect(route('zoom.index'));
        }
    }

    /**
     * Settings page: connect/disconnect Zoom.
     */
    public function zoomIndex(): \Illuminate\View\View
    {
        $data['zoomIntegrationExists'] = ZoomIntegration::whereUserId(getLogInUserId())->exists();
        return view('connect_zoom.index', compact('data'));
    }

    public function disconnect(): RedirectResponse
    {
        ZoomIntegration::whereUserId(getLogInUserId())->delete();
        Flash::success('Zoom disconnected successfully.');
        return redirect(route('zoom.index'));
    }
}