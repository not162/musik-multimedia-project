<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('studio_requests')) {
            Schema::create('studio_requests', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('song_id')->constrained()->cascadeOnDelete();
                $t->string('desired_style', 40)->default('halus_jernih');
                $t->string('note', 400)->nullable();
                $t->string('status', 24)->default('pending')->index();
                $t->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $t->string('admin_reply', 400)->nullable();
                $t->timestamp('processed_at')->nullable();
                $t->timestamps();
            });
        }

        if (Schema::hasTable('api_endpoint_rules')) {
            $exists = DB::table('api_endpoint_rules')->where('endpoint_key', 'user.studio')->exists();
            if (! $exists) {
                DB::table('api_endpoint_rules')->insert([
                    'group_name'         => 'user',
                    'endpoint_key'       => 'user.studio',
                    'uri_pattern'        => '/api/v1/studio',
                    'method'             => 'GET/PUT/POST',
                    'rate_limit_per_min' => 60,
                    'is_enabled'         => true,
                    'realtime_broadcast' => true,
                    'description'        => 'Studio Musik Pengguna (Terhubung real-time ke Studio Admin)',
                    'created_at'         => now(),
                    'updated_at'         => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_requests');
        if (Schema::hasTable('api_endpoint_rules')) {
            DB::table('api_endpoint_rules')->where('endpoint_key', 'user.studio')->delete();
        }
    }
};
