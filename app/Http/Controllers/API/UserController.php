<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EventSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

//use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
     

    public function authlogin(Request $request) {
    $validator = Validator::make($request->all(), [
        'email' => 'required|string|email|max:255',
        'password' => 'required|string|min:6',
    ]);

    if ($validator->fails()) {
        return response(['errors' => $validator->errors()->all()], 422);
    }

    $user = User::where('email', $request->email)->first();

    if ($user) {
        if (Hash::check($request->password, $user->password)) {
            $token = $user->createToken('Email Access');
            
            $response = [
                'token' => $token->plainTextToken,
                'domain_url' => $user->domain_url,
                'phone_number' => $user->phone_number
            ];
            return response($response, 200);
        } else {
            $response = ["message" => "Password mismatch"];
            return response($response, 422);
        }
    } else {
        $response = ["message" => 'User does not exist'];
        return response($response, 422);
    }
}

    
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }
        $user = User::where('email', $request->email)->first();
        if ($user) {
            if (Hash::check($request->password, $user->password)) {
                $token = $user->createToken('Email Access');
                $events = EventSchedule::where('user_id', $user->id)->get()->toArray();
                $response = ['token' => $token->plainTextToken,'events'=> $events];
                return response($response, 200);
            } else {
                $response = ["message" => "Password mismatch"];
                return response($response, 422);
            }
        } else {
            $response = ["message" =>'User does not exist'];
            return response($response, 422);
        }
    }
    
    
    
    public function qrscan(Request $request){
        $validator = Validator::make($request->all(), [
            
            'usrqr' => 'required|string',
        ]);
        if ($validator->fails())
        {
            return response(['errors'=>$validator->errors()->all()], 422);
        }
        $user = User::where('domain_url', $request->usrqr)->first();
        if ($user) {
                 $phone_number = $user->phone_number;
                  $response = [
            'message' => 'User found',
            'phone_number' => $phone_number,
        ];
                return response($response, 200);
            
        } else {
            $response = ["message" =>'User does not exist'];
            return response($response, 422);
        }
    }


    public function checkEvent(Request $request)
    {
        Log::info('checkEvent API called', $request->all());

        // Validate input
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:6',
            'caller_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('Validation failed', $validator->errors()->all());
            return response()->json(['errors' => $validator->errors()->all()], 422);
        }

        // Find user
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            Log::warning('User does not exist', ['email' => $request->email]);
            return response()->json(['message' => 'User does not exist'], 422);
        }

        // Check password
        if (!Hash::check($request->password, $user->password)) {
            Log::warning('Password mismatch', ['email' => $request->email]);
            return response()->json(['message' => 'Password mismatch'], 422);
        }

        // Normalize caller number: remove non-digits and take last 10 digits
        $callerNumber = substr(preg_replace('/\D/', '', $request->input('caller_number')), -10);
        Log::info('Caller number received', ['caller_number' => $callerNumber]);

        // Check if this number exists in the users table
        $callalinkUser = User::where('phone_number', 'LIKE', "%{$callerNumber}%")->first();
        $isCallalinkUser = $callalinkUser ? true : false;
        $callerName = $callalinkUser->first_name ?? null;

        // Use IST timezone (Asia/Kolkata)
        $now = Carbon::now('Asia/Kolkata');
        Log::info('Current time', ['now' => $now->toDateTimeString()]);

        // Find events for today and matching phone number
        $events = EventSchedule::where('phone_call', 'LIKE', "%{$callerNumber}%")
            ->where('schedule_date', $now->toDateString())
            ->get();

        $event = $events->filter(function ($item) use ($now) {
            Log::info('Checking event slot', ['slot_time' => $item->slot_time]);

            $parts = array_map('trim', explode('-', $item->slot_time));
            if (count($parts) !== 2) {
                Log::warning('Malformed slot_time', ['slot_time' => $item->slot_time]);
                return false;
            }

            [$start, $end] = $parts;

            try {
                // Parse start and end times in IST and set same date as $now
                $startTime = Carbon::createFromFormat('h:i A', $start, 'Asia/Kolkata')
                    ->setDate($now->year, $now->month, $now->day);
                $endTime = Carbon::createFromFormat('h:i A', $end, 'Asia/Kolkata')
                    ->setDate($now->year, $now->month, $now->day);

                Log::info('Parsed times', ['start' => $startTime->toTimeString(), 'end' => $endTime->toTimeString()]);
            } catch (\Exception $e) {
                Log::error('Error parsing time', ['slot_time' => $item->slot_time, 'error' => $e->getMessage()]);
                return false;
            }

            // Inclusive check: now >= start && now <= end
            return $now->between($startTime, $endTime, true);
        })->first();

        Log::info('Event found', ['event' => $event]);

        return response()->json([
            'allowed' => $event ? true : false,
            'eventname' => $event->name ?? null,
            'slot_time' => $event->slot_time ?? null,
            'is_callalink_user' => $isCallalinkUser,
            'username' => $callerName,
        ]);
    }


}
