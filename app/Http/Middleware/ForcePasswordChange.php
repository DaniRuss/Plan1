<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password
            && !$request->routeIs('password.first.edit', 'password.first.update', 'logout')) {
            return redirect()->route('password.first.edit');
        }

        return $next($request);
    }
}
