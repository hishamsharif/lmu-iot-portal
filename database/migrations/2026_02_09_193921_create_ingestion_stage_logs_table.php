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
        Schema::create('ingestion_stage_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('ingestion_message_id');
            $table->string('stage', 50);
            $table->string('status', 50);
            $table->unsignedInteger('duration_ms')->nullable();
            $table->jsonb('input_snapshot')->nullable();
            $table->jsonb('output_snapshot')->nullable();
            $table->jsonb('change_set')->nullable();
            $table->jsonb('errors')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('ingestion_message_id')
                ->references('id')
                ->on('ingestion_messages')
                ->cascadeOnDelete();

            $table->index(['ingestion_message_id', 'stage'], 'ingestion_stage_logs_message_stage_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ingestion_stage_logs');
    }
};
