<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            if (! Schema::hasColumn('songs', 'uploaded_by')) {
                $table->foreignId('uploaded_by')
                    ->nullable()
                    ->after('record_label_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('songs', 'voice_bitrate_kbps')) {
                $table->unsignedSmallInteger('voice_bitrate_kbps')
                    ->nullable()
                    ->after('media_format');
            }
        });
    }

    public function down(): void
    {
        Schema::table('songs', function (Blueprint $table) {
            if (Schema::hasColumn('songs', 'uploaded_by')) {
                $table->dropConstrainedForeignId('uploaded_by');
            }

            if (Schema::hasColumn('songs', 'voice_bitrate_kbps')) {
                $table->dropColumn('voice_bitrate_kbps');
            }
        });
    }
};
