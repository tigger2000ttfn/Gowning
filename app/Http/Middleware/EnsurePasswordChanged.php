<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * If the signed-in user is flagged must_change_password, force them to the
 * ChangePassword page. Everything else in the panel is blocked until they reset.
 * The change page, logout, and Livewire's update endpoint are always allowed so
 * the form can submit and the user can sign out, avoiding a redirect loop.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        if ($user && ($user->must_change_password ?? false)) {
            $allowed = $request->routeIs('filament.admin.pages.change-password')
                || $request->routeIs('filament.admin.auth.logout')
                || $request->is('*livewire/*')
                || $request->is('*/livewire/*');
            if (! $allowed) {
                return redirect()->route('filament.admin.pages.change-password');
            }
        }
        return $next($request);
    }
}
