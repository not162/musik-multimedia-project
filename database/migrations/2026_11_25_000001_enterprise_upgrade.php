<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint Enterprise Upgrade
 * Phase 2: Database & Storage + Phase 3: Auth tables + Phase 8: Recovery + Phase 9: Logs
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Phase 3: Enhance users table ─────────────────────────────
        Schema::table('users', function (Blueprint $t) {
            $t->string('avatar')->nullable()->after('role');
            $t->string('phone', 20)->nullable()->after('avatar');
            $t->boolean('is_active')->default(true)->after('phone');
            $t->timestamp('last_login_at')->nullable()->after('is_active');
            $t->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $t->unsignedTinyInteger('failed_login_attempts')->default(0)->after('last_login_ip');
            $t->timestamp('locked_until')->nullable()->after('failed_login_attempts');
            $t->index('role');
            $t->index('is_active');
        });

        // ── Phase 3: Personal Access Tokens (API auth) ───────────────
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        // ── Phase 4: Security audit log ──────────────────────────────
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 60);        // login, logout, create, update, delete, etc.
            $t->string('resource_type', 60)->nullable(); // Song, Album, User, etc.
            $t->unsignedBigInteger('resource_id')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->text('description')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['user_id', 'created_at']);
            $t->index(['action', 'created_at']);
            $t->index(['resource_type', 'resource_id']);
        });

        // ── Phase 5: Rate limit tracking (database driver) ───────────
        Schema::create('rate_limits', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->unsignedInteger('hits')->default(0);
            $t->timestamp('reset_at');
            $t->index('reset_at');
        });

        // ── Phase 9: Application error log ───────────────────────────
        Schema::create('error_logs', function (Blueprint $t) {
            $t->id();
            $t->string('level', 20)->default('error');  // emergency, alert, critical, error, warning
            $t->string('channel', 40)->default('app');
            $t->string('message', 500);
            $t->text('context')->nullable();             // JSON stringified
            $t->text('stack_trace')->nullable();
            $t->string('url', 500)->nullable();
            $t->string('method', 10)->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->boolean('is_resolved')->default(false);
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['level', 'is_resolved', 'created_at']);
            $t->index('channel');
        });

        // ── Phase 8: Backup history ──────────────────────────────────
        Schema::create('backup_logs', function (Blueprint $t) {
            $t->id();
            $t->string('filename');
            $t->string('disk', 20)->default('local');
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->string('status', 20)->default('completed'); // completed, failed, in_progress
            $t->text('notes')->nullable();
            $t->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // ── Phase 7: SEO metadata table ──────────────────────────────
        Schema::create('seo_metadata', function (Blueprint $t) {
            $t->id();
            $t->string('route_name', 100)->unique();
            $t->string('title', 160)->nullable();
            $t->string('description', 320)->nullable();
            $t->string('og_image')->nullable();
            $t->json('extra_meta')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_metadata');
        Schema::dropIfExists('backup_logs');
        Schema::dropIfExists('error_logs');
        Schema::dropIfExists('rate_limits');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('personal_access_tokens');

        Schema::table('users', function (Blueprint $t) {
            $t->dropIndex(['role']);
            $t->dropIndex(['is_active']);
            $t->dropColumn([
                'avatar', 'phone', 'is_active', 'last_login_at',
                'last_login_ip', 'failed_login_attempts', 'locked_until',
            ]);
        });
    }
};
