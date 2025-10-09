<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\EventSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\LoginRequest;
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
    
}
