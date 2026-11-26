<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $t) {
            if (! Schema::hasColumn('songs', 'training_split')) {
                $t->string('training_split', 20)->default('unassigned')->after('audio_meta')->index();
                $t->string('voice_preset', 40)->nullable()->after('training_split');
                $t->unsignedTinyInteger('voice_clarity_score')->nullable()->after('voice_preset');
                $t->unsignedTinyInteger('voice_smoothness_score')->nullable()->after('voice_clarity_score');
                $t->decimal('noise_reduction_db', 4, 1)->default(0)->after('voice_smoothness_score');
                $t->boolean('is_voice_smoothed')->default(false)->after('noise_reduction_db')->index();
                $t->foreignId('labeled_by')->nullable()->after('is_voice_smoothed')->constrained('users')->nullOnDelete();
                $t->timestamp('labeled_at')->nullable()->after('labeled_by');
            }
        });

        if (! Schema::hasTable('audio_training_samples')) {
            Schema::create('audio_training_samples', function (Blueprint $t) {
                $t->id();
                $t->foreignId('song_id')->constrained()->cascadeOnDelete();
                $t->string('label_name', 60);
                $t->string('dataset_split', 20)->default('train')->index();
                $t->unsignedTinyInteger('target_clarity')->default(88);
                $t->unsignedTinyInteger('target_smoothness')->default(90);
                $t->decimal('noise_reduction_db', 4, 1)->default(8.0);
                $t->json('extracted_features')->nullable();
                $t->json('target_eq_bands');
                $t->text('notes')->nullable();
                $t->foreignId('labeled_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('ai_training_runs')) {
            Schema::create('ai_training_runs', function (Blueprint $t) {
                $t->id();
                $t->string('model_name', 100);
                $t->string('architecture', 80);
                $t->unsignedInteger('train_count')->default(0);
                $t->unsignedInteger('val_count')->default(0);
                $t->unsignedSmallInteger('epochs')->default(120);
                $t->decimal('train_loss', 8, 4)->nullable();
                $t->decimal('val_loss', 8, 4)->nullable();
                $t->decimal('val_accuracy', 5, 2)->nullable();
                $t->decimal('clarity_gain_pct', 5, 2)->nullable();
                $t->json('weights')->nullable();
                $t->json('metrics_detail')->nullable();
                $t->string('status', 30)->default('trained');
                $t->foreignId('trained_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_training_runs');
        Schema::dropIfExists('audio_training_samples');
        Schema::table('songs', function (Blueprint $t) {
            foreach ([
                'labeled_by', 'labeled_at', 'is_voice_smoothed', 'noise_reduction_db',
                'voice_smoothness_score', 'voice_clarity_score', 'voice_preset', 'training_split',
            ] as $col) {
                if ($col === 'labeled_by' && Schema::hasColumn('songs', 'labeled_by')) {
                    $t->dropConstrainedForeignId('labeled_by');
                } elseif (Schema::hasColumn('songs', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
