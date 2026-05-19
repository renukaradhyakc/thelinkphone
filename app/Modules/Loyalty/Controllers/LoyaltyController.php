<?php

namespace App\Modules\Loyalty\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LoyaltyController extends Controller
{
    public function index()
    {
        return view('loyalty.dashboard1');
    }
}