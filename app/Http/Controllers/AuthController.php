<?php
namespace App\Http\Controllers;

use App\Events\SecurityAlertEvent;
use App\Models\{AuditLog, User};
use App\Services\LrsService;
use App\Support\SafeBroadcast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()    { return view('auth.login'); }
    public function showRegister() { return view('auth.register'); }

    public function login(Request $request)
    {
        $cred = $request->validate(['email' => 'required|email', 'password' => 'required']);

        $user = User::where('email', $cred['email'])->first();

        if ($user && $user->isLocked()) {
            $mins = max(1, now()->diffInMinutes($user->locked_until));
            return back()->withErrors(['email' => "Akun terkunci. Coba lagi dalam {$mins} menit."])->onlyInput('email');
        }

        if (! Auth::attempt($cred, $request->boolean('remember'))) {
            if ($user) {
                $user->increment('failed_login_attempts');
                if ($user->failed_login_attempts >= 5) {
                    $user->update(['locked_until' => now()->addMinutes(15)]);
                    AuditLog::record('account_locked', 'User', $user->id,
                        description: "Akun {$user->email} dikunci 15 menit setelah 5 percobaan gagal dari IP {$request->ip()}");
                    SafeBroadcast::dispatch(new SecurityAlertEvent(
                        'account_locked',
                        "Akun {$user->email} dikunci setelah 5 percobaan gagal.",
                        $request->ip()
                    ));
                }
            }
            return back()->withErrors(['email' => 'Email atau kata sandi salah.'])->onlyInput('email');
        }

        if (! $user->is_active) {
            Auth::logout();
            return back()->withErrors(['email' => 'Akun dinonaktifkan. Hubungi admin.'])->onlyInput('email');
        }

        $user->update([
            'failed_login_attempts' => 0,
            'locked_until'          => null,
            'last_login_at'         => now(),
            'last_login_ip'         => $request->ip(),
        ]);

        $request->session()->regenerate();

        AuditLog::record('login', 'User', $user->id, description: "Login web dari {$request->ip()}");
        LrsService::record('logged_in', 'Auth', $user->id, $user->email, ['channel' => 'web']);

        $destination = Auth::user()->isAdmin() ? route('admin.dashboard') : route('home');
        return redirect()->intended($destination);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:80',
            'email'    => 'required|email|max:120|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
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

        Auth::login($user);
        $request->session()->regenerate();

        AuditLog::record('register', 'User', $user->id, description: "Registrasi web dari {$request->ip()}");
        LrsService::record('registered', 'User', $user->id, $user->email, ['channel' => 'web']);

        return redirect()->route('home')->with('ok', 'Selamat datang di Musikku! Semua fitur akun terdaftar kini terbuka.');
    }

    public function logout(Request $request)
    {
        $uid = Auth::id();
        $email = Auth::user()?->email;
        AuditLog::record('logout', 'User', $uid);
        LrsService::record('logged_out', 'Auth', $uid, $email, ['channel' => 'web']);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home');
    }
}
