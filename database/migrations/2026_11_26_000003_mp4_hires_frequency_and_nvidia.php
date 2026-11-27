<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $t) {
            if (! Schema::hasColumn('songs', 'media_format')) {
                $t->string('media_format', 12)->default('mp3')->after('file_path');
                $t->unsignedSmallInteger('target_sample_rate_khz')->nullable()->after('noise_reduction_db');
                $t->string('hires_file_path', 255)->nullable()->after('target_sample_rate_khz');
            }
        });

        Schema::table('ai_provider_settings', function (Blueprint $t) {
            if (! Schema::hasColumn('ai_provider_settings', 'compute_name')) {
                $t->string('compute_name', 120)->nullable()->after('base_url');
                $t->text('compute_key_encrypted')->nullable()->after('compute_name');
                $t->string('compute_key_hint', 32)->nullable()->after('compute_key_encrypted');
            }
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $t) {
            foreach (['hires_file_path', 'target_sample_rate_khz', 'media_format'] as $col) {
                if (Schema::hasColumn('songs', $col)) {
                    $t->dropColumn($col);
                }
            }
        });

        Schema::table('ai_provider_settings', function (Blueprint $t) {
            foreach (['compute_key_hint', 'compute_key_encrypted', 'compute_name'] as $col) {
                if (Schema::hasColumn('ai_provider_settings', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
