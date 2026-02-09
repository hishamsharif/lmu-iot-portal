<?php

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
        Schema::table('device_command_logs', function (Blueprint $table) {
            $table->foreignId('response_schema_version_topic_id')
                ->nullable()
                ->after('schema_version_topic_id')
                ->constrained('schema_version_topics')
                ->nullOnDelete();
            $table->uuid('correlation_id')->nullable()->after('command_payload');
            $table->index('correlation_id', 'device_command_logs_correlation_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_command_logs', function (Blueprint $table) {
            $table->dropIndex('device_command_logs_correlation_id_index');
            $table->dropForeign(['response_schema_version_topic_id']);
            $table->dropColumn(['response_schema_version_topic_id', 'correlation_id']);
        });
    }
};
