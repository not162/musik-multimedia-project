<?php

use Illuminate\Support\Facades\Broadcast;

/**
 * Broadcasting Authorization Channels (Laravel Reverb & Pusher)
 */

// Public channel: musikku.live (no auth callback needed)

// Registered User private notifications
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id && $user->is_active;
});

// Registered User presence channel: live listeners online
Broadcast::channel('musikku.listeners', function ($user) {
    if (! $user || ! $user->is_active) {
        return false;
    }

    return [
        'id'   => $user->id,
        'name' => $user->name,
        'role' => $user->role,
    ];
});

// Admin-only real-time alerts (Phase 9 Error Tracking & Security Alerts)
Broadcast::channel('admin.alerts', function ($user) {
    return $user && $user->is_active && $user->isAdmin();
});
