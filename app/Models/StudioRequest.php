<?php

namespace App\Models;

use App\Events\{StudioRequestUpdatedEvent, UserNotificationEvent};
use App\Support\SafeBroadcast;
use Illuminate\Database\Eloquent\Model;

class StudioRequest extends Model
{
    protected $fillable = [
        'user_id', 'song_id', 'desired_style', 'note', 'status',
        'processed_by', 'admin_reply', 'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function song()
    {
        return $this->belongsTo(Song::class);
    }

    public function processor()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Tandai seluruh permintaan User untuk lagu ini selesai & kirim notifikasi real-time ke Studio Musik User.
     */
    public static function completePendingForSong(Song $song, ?int $adminId = null, ?string $reply = null): int
    {
        $pending = self::where('song_id', $song->id)->where('status', 'pending')->get();
        if ($pending->isEmpty()) {
            return 0;
        }

        $message = $reply ?: "Suara lagu \"{$song->title}\" sudah dirapikan dan dihaluskan lewat Studio.";
        foreach ($pending as $req) {
            $req->update([
                'status'       => 'completed',
                'processed_by' => $adminId,
                'admin_reply'  => $message,
                'processed_at' => now(),
            ]);
            SafeBroadcast::dispatch(new StudioRequestUpdatedEvent($req->fresh()));
            SafeBroadcast::dispatch(new UserNotificationEvent(
                $req->user_id,
                'Studio Musik Diperbarui',
                $message,
                'success'
            ));
        }

        return $pending->count();
    }
}
