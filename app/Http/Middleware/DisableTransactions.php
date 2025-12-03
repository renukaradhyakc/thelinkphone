<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DisableTransactions
{
    public function handle(Request $request, Closure $next)
    {
        // Option 1: For non-admin users
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Access Denied');
        }

        // Option 2: Or disable feature completely
        // abort(403, 'Transactions feature is disabled.');

        return $next($request);
    }
}
