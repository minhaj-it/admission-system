<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Usage: EnsureAccountType::class . ':staff'  or  ':student'. Also signs out deactivated accounts. */
class EnsureAccountType
{
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $user = $request->user();

        if ($user && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        abort_unless($user && $user->account_type === $type, 403);

        return $next($request);
    }
}
