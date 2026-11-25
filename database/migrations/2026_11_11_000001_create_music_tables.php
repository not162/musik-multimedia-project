<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('user'); // user | admin
        });

        Schema::create('artists', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('bio')->nullable();
            $t->string('photo')->nullable();
            $t->timestamps();
        });

        Schema::create('genres', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });

        Schema::create('albums', function (Blueprint $t) {
            $t->id();
            $t->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('cover')->nullable();
            $t->unsignedSmallInteger('release_year')->nullable();
            $t->timestamps();
        });

        Schema::create('songs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $t->foreignId('album_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('genre_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('file_path');
            $t->string('cover')->nullable();
            $t->text('lyrics')->nullable();
            $t->unsignedInteger('duration')->default(0); // detik
            $t->unsignedBigInteger('play_count')->default(0);
            $t->timestamps();
            $t->index('title');
        });

        Schema::create('playlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->boolean('is_public')->default(false);
            $t->timestamps();
        });

        Schema::create('playlist_song', function (Blueprint $t) {
            $t->id();
            $t->foreignId('playlist_id')->constrained()->cascadeOnDelete();
            $t->foreignId('song_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('position')->default(0);
            $t->unique(['playlist_id', 'song_id']);
        });

        Schema::create('favorites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('song_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'song_id']);
        });

        // user_id boleh null: pemutaran oleh tamu tetap dihitung untuk chart
        Schema::create('play_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('song_id')->constrained()->cascadeOnDelete();
            $t->timestamp('played_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['play_histories','favorites','playlist_song','playlists','songs','albums','genres','artists'] as $tbl) {
            Schema::dropIfExists($tbl);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role'));
    }
};
