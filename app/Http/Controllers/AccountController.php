<?php

namespace App\Http\Controllers;

use App\Events\{SecurityAlertEvent, UserNotificationEvent};
use App\Models\{AuditLog, User};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\{JsonResponse, Request};
use Illuminate\Support\Facades\{Hash, RateLimiter, Storage};
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $tokens = $user->isAdmin() ? $user->tokens()->latest()->get() : collect();
        $recentAudits = $user->isAdmin()
            ? AuditLog::where('user_id', $user->id)->latest('created_at')->limit(10)->get()
            : collect();

        return view('account.index', [
            'user'         => $user,
            'tokens'       => $tokens,
            'recentAudits' => $recentAudits,
            'features'     => $user->unlockedFeatures(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:80',
            'phone'         => 'nullable|string|max:20',
            'avatar'        => 'nullable|image|mimes:jpg,jpeg,png,webp,gif|max:2048',
            'remove_avatar' => 'nullable|boolean',
        ]);
        unset($data['avatar'], $data['remove_avatar']);

        $user = $request->user();
        $old  = $user->only(['name', 'phone', 'avatar']);

        if ($request->boolean('remove_avatar') && $user->avatar) {
            Storage::disk('public')->delete($user->avatar);
            $data['avatar'] = null;
        }
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($data);

        AuditLog::record('update_profile', 'User', $user->id, $old, $user->only(['name', 'phone', 'avatar']));
        SafeBroadcast::dispatch(new UserNotificationEvent(
            $user->id,
            'Profil Diperbarui',
            'Profil dan gambar logo akun Anda berhasil disimpan.',
            'ok'
        ));

        return back()->with('ok', 'Profil dan gambar logo akun berhasil diperbarui.');
    }

    public function avatar(User $user)
    {
        abort_unless($user->avatar && Storage::disk('public')->exists($user->avatar), 404);
        return response()->file(Storage::disk('public')->path($user->avatar), [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password'         => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini salah.']);
        }

        $hashed = User::hashPasswordWithSalt($request->password);
        $user->update($hashed);

        AuditLog::record('change_password', 'User', $user->id);
        LrsService::record('changed_password', 'Auth', $user->id, $user->email);

        return back()->with('ok', 'Kata sandi baru (bcrypt + salt acak) berhasil disimpan.');
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

        return response()->json(['ok' => true, 'preferences' => $prefs]);
    }

    public function createToken(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403, 'Akses pembuatan token API khusus akun Admin.');
        $data = $request->validate(['token_name' => 'required|string|max:60']);
        $user = $request->user();

        $abilities = ['admin', 'user'];
        $token = $user->createToken($data['token_name'], $abilities)->plainTextToken;

        AuditLog::record('create_api_token', 'User', $user->id, description: "Created API token: {$data['token_name']}");
        LrsService::record('created_token', 'ApiToken', $user->id, $data['token_name']);

        return back()->with('ok', 'Token API dibuat. Salin sekarang, tidak akan ditampilkan lagi.')
            ->with('new_api_token', $token);
    }

    public function revokeToken(int $tokenId)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Akses pencabutan token API khusus akun Admin.');
        $user = auth()->user();
        $user->tokens()->where('id', $tokenId)->delete();

        AuditLog::record('revoke_api_token', 'User', $user->id, description: "Revoked token #{$tokenId}");
        return back()->with('ok', 'Token API dicabut.');
    }
}
