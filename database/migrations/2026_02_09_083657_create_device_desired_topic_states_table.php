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
        Schema::create('device_desired_topic_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schema_version_topic_id')
                ->constrained('schema_version_topics')
                ->cascadeOnDelete();
            $table->jsonb('desired_payload');
            $table->uuid('correlation_id')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'schema_version_topic_id'], 'device_desired_topic_states_device_topic_unique');
            $table->index('correlation_id', 'device_desired_topic_states_correlation_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_desired_topic_states');
    }
};
