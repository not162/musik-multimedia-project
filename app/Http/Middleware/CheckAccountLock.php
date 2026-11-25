<?php
/**
 * Phase 3: Account lockout after failed login attempts.
 */

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckAccountLock
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $minutes = now()->diffInMinutes($user->locked_until);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "Akun terkunci. Coba lagi dalam {$minutes} menit.",
                ], 423);
            }

            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => "Akun terkunci. Coba lagi dalam {$minutes} menit."]);
        }

        // Check if account is deactivated
        if ($user && ! $user->is_active) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Akun dinonaktifkan.'], 403);
            }

            auth()->logout();
            return redirect()->route('login')
                ->withErrors(['email' => 'Akun Anda telah dinonaktifkan. Hubungi admin.']);
        }

        return $next($request);
    }
}
