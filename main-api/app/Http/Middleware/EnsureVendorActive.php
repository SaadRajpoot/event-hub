<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureVendorActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isVendor()) {
            $vendor = $user->vendor;
            if (!$vendor || $vendor->status !== 'active') {
                return response()->json([
                    'message' => 'Your vendor account is not active. Please wait for approval.',
                ], 403);
            }
        }

        return $next($request);
    }
}
