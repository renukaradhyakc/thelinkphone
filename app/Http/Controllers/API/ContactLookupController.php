<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\ContactLookupService;
use Illuminate\Http\Request;

class ContactLookupController extends Controller
{
    public function __construct(protected ContactLookupService $lookupService) {}

    public function lookup(Request $request)
    {
        $validated = $request->validate([
            'numbers' => 'required|array|min:1|max:1000',
            'numbers.*' => 'string',
        ]);

        $results = $this->lookupService->lookup($validated['numbers']);

        return response()->json([
             'results' => empty($results) ? (object) [] : $results,
        ]);
    }
}