<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Enterprise Continuation:
 * - Phase 2/6/7: MySQL cache, cache_locks, jobs, failed_jobs tables (load balancing & queue readiness)
 * - Phase 3: Explicit password_salt & user preferences JSON on users
 * - Phase 4: Local Record Store (LRS Local) table with tamper-evident HMAC signatures
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'password_salt')) {
                $t->string('password_salt', 64)->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'preferences')) {
                $t->json('preferences')->nullable()->after('is_active');
            }
        });

        if (! Schema::hasTable('lrs_statements')) {
            Schema::create('lrs_statements', function (Blueprint $t) {
                $t->id();
                $t->uuid('statement_id')->unique();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->string('actor_name', 120)->default('Tamu Publik');
                $t->string('actor_email', 160)->nullable();
                $t->string('verb', 60)->index();          // played, favorited, registered, login, searched, created_playlist
                $t->string('object_type', 60)->index();   // Song, Playlist, Artist, Auth, Search
                $t->unsignedBigInteger('object_id')->nullable();
                $t->string('object_name', 200)->nullable();
                $t->json('context')->nullable();
                $t->string('ip_address', 45)->nullable();
                $t->string('signature', 128);             // HMAC-SHA256 salted integrity hash
                $t->timestamp('stored_at')->useCurrent()->index();
            });
        }

        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->mediumText('value');
                $t->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('cache_locks')) {
            Schema::create('cache_locks', function (Blueprint $t) {
                $t->string('key')->primary();
                $t->string('owner');
                $t->integer('expiration')->index();
            });
        }

        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $t) {
                $t->id();
                $t->string('queue')->index();
                $t->longText('payload');
                $t->unsignedTinyInteger('attempts');
                $t->unsignedInteger('reserved_at')->nullable();
                $t->unsignedInteger('available_at');
                $t->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $t) {
                $t->id();
                $t->string('uuid')->unique();
                $t->text('connection');
                $t->text('queue');
                $t->longText('payload');
                $t->longText('exception');
                $t->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('lrs_statements');

        Schema::table('users', function (Blueprint $t) {
            if (Schema::hasColumn('users', 'preferences')) {
                $t->dropColumn('preferences');
            }
            if (Schema::hasColumn('users', 'password_salt')) {
                $t->dropColumn('password_salt');
            }
        });
    }
};
