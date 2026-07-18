<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_admin) {
            abort(403, 'Acesso restrito ao painel administrativo.');
        }

        if ($user->is_blocked) {
            abort(403, 'Conta bloqueada.');
        }

        return $next($request);
    }
}
