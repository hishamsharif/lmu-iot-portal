<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('device_telemetry_logs', function (Blueprint $table) {
            $table->uuid('ingestion_message_id')->nullable()->after('schema_version_topic_id');
            $table->jsonb('validation_errors')->nullable()->after('raw_payload');
            $table->jsonb('mutated_values')->nullable()->after('validation_errors');
            $table->string('processing_state', 50)->default('processed')->after('validation_status');

            $table->foreign('ingestion_message_id')
                ->references('id')
                ->on('ingestion_messages')
                ->nullOnDelete();

            $table->index('ingestion_message_id');
            $table->index('processing_state');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_telemetry_logs', function (Blueprint $table) {
            $table->dropForeign(['ingestion_message_id']);
            $table->dropIndex(['ingestion_message_id']);
            $table->dropIndex(['processing_state']);
            $table->dropColumn(['ingestion_message_id', 'validation_errors', 'mutated_values', 'processing_state']);
        });
    }
};
