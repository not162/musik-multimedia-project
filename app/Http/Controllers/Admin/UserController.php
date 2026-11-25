<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::withCount('playlists')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->latest()->paginate(25)->withQueryString();
        return view('admin.users', compact('users'));
    }

    public function toggleRole(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa mengubah peran sendiri.');
        $old = $user->role;
        $new = $user->isAdmin() ? 'user' : 'admin';
        $user->update(['role' => $new]);

        AuditLog::record('toggle_role', 'User', $user->id, ['role' => $old], ['role' => $new]);
        return back()->with('ok', "Peran {$user->name} sekarang: {$new}.");
    }

    public function toggleStatus(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa menonaktifkan diri sendiri.');
        $old = $user->is_active;
        $user->update(['is_active' => ! $old]);
        if ($old) {
            $user->tokens()->delete();
        }
        AuditLog::record('toggle_status', 'User', $user->id, ['is_active' => $old], ['is_active' => ! $old]);
        return back()->with('ok', "Status akun {$user->name}: " . ($user->is_active ? 'Aktif' : 'Nonaktif') . '.');
    }

    public function unlock(User $user)
    {
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        AuditLog::record('unlock_user', 'User', $user->id);
        return back()->with('ok', "Kunci akun {$user->name} dibuka.");
    }

    public function destroy(User $user)
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa menghapus akun sendiri.');
        AuditLog::record('delete', 'User', $user->id, $user->only(['name', 'email', 'role']));
        $user->tokens()->delete();
        $user->delete();
        return back()->with('ok', "Pengguna {$user->name} dihapus.");
    }
}
