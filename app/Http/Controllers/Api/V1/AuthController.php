<?php
/**
 * Phase 1 + 3: API Auth Controller
 * Register, Login (with brute-force protection & hashing+salting), Logout, Profile, Preferences, Password change.
 */

namespace App\Http\Controllers\Api\V1;

use App\Events\SecurityAlertEvent;
use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Hash, RateLimiter};
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:80',
            'email'    => 'required|email|max:120|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $hashed = User::hashPasswordWithSalt($data['password']);

        $user = User::create([
            'name'          => $data['name'],
            'email'         => $data['email'],
            'password'      => $hashed['password'],
            'password_salt' => $hashed['password_salt'],
            'role'          => 'user',
            'is_active'     => true,
            'preferences'   => ['eq_preset' => 'normal', 'speed' => 1.0],
        ]);

        $token = $user->createToken('api', ['user'])->plainTextToken;

        AuditLog::record('register', 'User', $user->id, description: "User {$user->email} registered via API.");
        LrsService::record('registered', 'User', $user->id, $user->email, ['channel' => 'api']);

        return response()->json([
            'message'           => 'Registrasi berhasil.',
            'user'              => $this->userPayload($user),
            'unlocked_features' => $user->unlockedFeatures(),
            'token'             => $token,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $throttleKey = 'login:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'message' => "Terlalu banyak percobaan. Coba lagi dalam {$seconds} detik.",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            $mins = max(1, now()->diffInMinutes($user->locked_until));
            return response()->json([
                'message' => "Akun terkunci. Coba lagi dalam {$mins} menit.",
            ], 423);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, 300);

            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 5) {
                    $user->update(['locked_until' => now()->addMinutes(15)]);
                    AuditLog::record('account_locked', 'User', $user->id, description: 'Locked after 5 failed API login attempts.');
                    SafeBroadcast::dispatch(new SecurityAlertEvent('account_locked', "Akun {$user->email} terkunci setelah 5 gagal login API.", $request->ip()));
                }
            }

            return response()->json(['message' => 'Email atau kata sandi salah.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun dinonaktifkan. Hubungi admin.'], 403);
        }

        RateLimiter::clear($throttleKey);
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => $request->ip(),
        ]);

        $user->tokens()->where('name', 'api')->delete();
        $abilities = $user->isAdmin() ? ['admin', 'user'] : ['user'];
        $token = $user->createToken('api', $abilities)->plainTextToken;

        AuditLog::record('login', 'User', $user->id, description: "Login via API from {$request->ip()}");
        LrsService::record('logged_in', 'Auth', $user->id, $user->email, ['channel' => 'api']);

        return response()->json([
            'message'           => 'Login berhasil.',
            'user'              => $this->userPayload($user),
            'unlocked_features' => $user->unlockedFeatures(),
            'token'             => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::record('logout', 'User', $request->user()->id);
        LrsService::record('logged_out', 'Auth', $request->user()->id, $request->user()->email, ['channel' => 'api']);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        return response()->json([
            'user'              => $this->userPayload($user),
            'unlocked_features' => $user->unlockedFeatures(),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:80',
            'phone' => 'sometimes|nullable|string|max:20',
        ]);

        $user = $request->user();
        $old  = $user->only(array_keys($data));
        $user->update($data);

        AuditLog::record('update_profile', 'User', $user->id, $old, $data);

        return response()->json([
            'message' => 'Profil diperbarui.',
            'user'    => $this->userPayload($user->fresh()),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eq_preset'            => 'sometimes|string|in:normal,bass,vocal,lofi,rock,acoustic,electronic,custom,auto_genre,adaptive',
            'eq_mode'              => 'sometimes|string|in:manual,auto_genre,adaptive',
            'eq_direction'         => 'sometimes|string|in:balanced,warm_bass,bright_clear,punchy,focus_study',
            'eq_intensity'         => 'sometimes|integer|min:0|max:100',
            'eq_auto_gain'         => 'sometimes|boolean',
            'eq_macro'             => 'sometimes|array',
            'eq_macro.bass_tilt'   => 'sometimes|numeric|min:-6|max:6',
            'eq_macro.vocal_focus' => 'sometimes|numeric|min:-6|max:6',
            'eq_macro.treble_air'  => 'sometimes|numeric|min:-6|max:6',
            'eq_bands'             => 'sometimes|array|size:10',
            'eq_bands.*'           => 'numeric|min:-12|max:12',
            'speed'                => 'sometimes|numeric|min:0.5|max:2.0',
        ]);

        $user  = $request->user();
        $prefs = array_merge($user->preferences ?? [], $data);
        $user->update(['preferences' => $prefs]);

        return response()->json(['message' => 'Preferensi disimpan.', 'preferences' => $prefs]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json(['message' => 'Password lama salah.'], 422);
        }

        $hashed = User::hashPasswordWithSalt($request->password);
        $user->update($hashed);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        AuditLog::record('change_password', 'User', $user->id);
        LrsService::record('changed_password', 'Auth', $user->id, $user->email, ['channel' => 'api']);

        return response()->json(['message' => 'Password berhasil diubah.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id'            => $user->id,
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => $user->role,
            'phone'         => $user->phone,
            'is_active'     => $user->is_active,
            'preferences'   => $user->preferences ?? ['eq_preset' => 'normal', 'speed' => 1.0],
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at'    => $user->created_at->toIso8601String(),
        ];
    }
}
