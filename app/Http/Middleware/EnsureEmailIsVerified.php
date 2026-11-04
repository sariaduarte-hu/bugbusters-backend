<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Houdt accounts zonder bevestigd e-mailadres buiten de API.
 * Nieuwe accounts krijgen pas na de code een token; dit vangt oude tokens van
 * accounts die van voor de verificatie komen.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->email_verified_at === null) {
            return response()->json([
                'message' => 'Bevestig eerst je e-mailadres.',
                'verification_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
