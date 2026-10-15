<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->active || ! $request->user()->hasActiveRole() || ! in_array($request->user()->accessScope(), ['admin', 'docente', 'padre'])) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(401, 'Tu cuenta no está activa. Contacta con la administración.');
        }

        return $next($request);
    }
}
