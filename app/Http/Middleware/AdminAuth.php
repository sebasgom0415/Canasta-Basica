<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        if (!session('admin_auth')) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'error' => 'No autorizado.'], 401);
            }
            return redirect()->route('admin.index');
        }

        return $next($request);
    }
}
