<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\{AuditLog, User};
use Illuminate\Http\{JsonResponse, Request};

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::withCount('playlists')
            ->when($request->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->query('role'), fn ($q, $r) => $q->where('role', $r))
            ->when($request->has('active'), fn ($q) => $q->where('is_active', $request->boolean('active')))
            ->latest()
            ->paginate(25)->withQueryString();

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => $user->loadCount(['playlists', 'favoriteSongs', 'playHistories']),
        ]);
    }

    public function toggleRole(User $user): JsonResponse
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa mengubah peran sendiri.');

        $old = $user->role;
        $new = $user->isAdmin() ? 'user' : 'admin';
        $user->update(['role' => $new]);

        AuditLog::record('toggle_role', 'User', $user->id, ['role' => $old], ['role' => $new]);

        return response()->json([
            'message' => "Peran {$user->name} diubah menjadi {$new}.",
            'user'    => $user->fresh(),
        ]);
    }

    public function toggleStatus(User $user): JsonResponse
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa mengubah status sendiri.');

        $old = $user->is_active;
        $user->update(['is_active' => ! $old]);

        AuditLog::record('toggle_status', 'User', $user->id, ['is_active' => $old], ['is_active' => ! $old]);

        if ($old) {
            $user->tokens()->delete();
        }

        return response()->json([
            'message' => $user->is_active ? "Akun {$user->name} diaktifkan." : "Akun {$user->name} dinonaktifkan.",
            'user'    => $user->fresh(),
        ]);
    }

    public function unlock(User $user): JsonResponse
    {
        $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
        AuditLog::record('unlock_user', 'User', $user->id);

        return response()->json(['message' => "Akun {$user->name} dibuka kuncinya.", 'user' => $user->fresh()]);
    }

    public function destroy(User $user): JsonResponse
    {
        abort_if($user->id === auth()->id(), 422, 'Tidak bisa menghapus akun sendiri.');

        AuditLog::record('delete', 'User', $user->id, $user->only(['name', 'email', 'role']));
        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => "Pengguna {$user->name} dihapus."]);
    }
}
