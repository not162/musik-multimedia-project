<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('record_labels')) {
            Schema::create('record_labels', function (Blueprint $t) {
                $t->id();
                $t->string('name', 120);
                $t->string('slug', 140)->unique();
                $t->string('country', 80)->nullable();
                $t->unsignedSmallInteger('founded_year')->nullable();
                $t->string('website', 255)->nullable();
                $t->text('description')->nullable();
                $t->string('logo')->nullable();
                $t->boolean('is_featured')->default(true)->index();
                $t->timestamps();
            });
        }

        Schema::table('albums', function (Blueprint $t) {
            if (! Schema::hasColumn('albums', 'record_label_id')) {
                $t->foreignId('record_label_id')->nullable()->after('artist_id')->constrained('record_labels')->nullOnDelete();
            }
        });

        Schema::table('songs', function (Blueprint $t) {
            if (! Schema::hasColumn('songs', 'record_label_id')) {
                $t->foreignId('record_label_id')->nullable()->after('genre_id')->constrained('record_labels')->nullOnDelete();
            }
            if (! Schema::hasColumn('songs', 'is_ai_finetuned')) {
                $t->boolean('is_ai_finetuned')->default(false)->after('play_count')->index();
                $t->string('ai_mood', 60)->nullable()->after('is_ai_finetuned');
                $t->unsignedSmallInteger('ai_tempo_bpm')->nullable()->after('ai_mood');
                $t->string('ai_direction', 40)->nullable()->after('ai_tempo_bpm');
                $t->json('ai_eq_bands')->nullable()->after('ai_direction');
                $t->decimal('ai_preamp_db', 5, 2)->default(0)->after('ai_eq_bands');
                $t->decimal('ai_pitch_rate', 4, 2)->default(1.00)->after('ai_preamp_db');
                $t->text('ai_mastering_notes')->nullable()->after('ai_pitch_rate');
                $t->timestamp('ai_finetuned_at')->nullable()->after('ai_mastering_notes');
                $t->json('audio_meta')->nullable()->after('ai_finetuned_at');
            }
        });

        if (! Schema::hasTable('ai_provider_settings')) {
            Schema::create('ai_provider_settings', function (Blueprint $t) {
                $t->id();
                $t->string('provider', 40)->default('gemini'); // gemini | openai | custom
                $t->string('model', 100)->default('gemini-2.5-flash');
                $t->string('base_url', 255)->nullable();
                $t->text('api_key_encrypted')->nullable();
                $t->string('key_hint', 32)->nullable();
                $t->boolean('auto_finetune_realtime')->default(true);
                $t->boolean('is_active')->default(true);
                $t->timestamp('last_tested_at')->nullable();
                $t->string('last_status', 200)->nullable();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('api_endpoint_rules')) {
            Schema::create('api_endpoint_rules', function (Blueprint $t) {
                $t->id();
                $t->string('group_name', 30)->index(); // public | user | admin
                $t->string('endpoint_key', 80)->unique();
                $t->string('uri_pattern', 160);
                $t->string('method', 20)->default('GET');
                $t->unsignedSmallInteger('rate_limit_per_min')->default(60);
                $t->boolean('is_enabled')->default(true);
                $t->boolean('realtime_broadcast')->default(true);
                $t->string('description', 255)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('api_endpoint_rules');
        Schema::dropIfExists('ai_provider_settings');

        Schema::table('songs', function (Blueprint $t) {
            if (Schema::hasColumn('songs', 'record_label_id')) {
                $t->dropConstrainedForeignId('record_label_id');
            }
            foreach (['is_ai_finetuned','ai_mood','ai_tempo_bpm','ai_direction','ai_eq_bands','ai_preamp_db','ai_pitch_rate','ai_mastering_notes','ai_finetuned_at','audio_meta'] as $col) {
                if (Schema::hasColumn('songs', $col)) $t->dropColumn($col);
            }
        });

        Schema::table('albums', function (Blueprint $t) {
            if (Schema::hasColumn('albums', 'record_label_id')) {
                $t->dropConstrainedForeignId('record_label_id');
            }
        });

        Schema::dropIfExists('record_labels');
    }
};
