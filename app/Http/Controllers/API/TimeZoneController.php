<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;

class TimeZoneController extends Controller
{
    public function index()
    {
        return response()->json([
            'timezones' => User::TIME_ZONE_ARRAY,
        ]);
    }
}