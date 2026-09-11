<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ProtectBrainViewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();

        // Redirect URL lama /_laravel-brain → /_brain-logic
        if (str_starts_with($path, '_laravel-brain')) {
            $newPath = str_replace('_laravel-brain', '_brain-logic', $request->getRequestUri());
            return redirect($newPath, 301);
        }

        if (! str_starts_with($path, '_brain-logic')) {
            return $next($request);
        }

        // Cek session auth (web guard)
        if (! Auth::check()) {
            session(['url.intended' => $request->url()]);
            return redirect()->route('login')->with('info', 'Silakan login untuk mengakses Brain Viewer.');
        }

        // Hanya Super Admin dan Admin
        /** @var \App\Models\User $authUser */
        $authUser = Auth::user();
        if (! $authUser->hasAnyRole(['Super Admin', 'Admin'])) {
            abort(403, 'Hanya Admin yang bisa mengakses Brain Viewer.');
        }

        return $next($request);
    }
}
