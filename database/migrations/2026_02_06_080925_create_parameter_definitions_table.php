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
        Schema::create('parameter_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schema_version_topic_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label', 255);
            $table->string('json_path', 255);
            $table->string('type', 50);
            $table->string('unit', 50)->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('is_critical')->default(false);
            $table->jsonb('validation_rules')->nullable();
            $table->jsonb('control_ui')->nullable();
            $table->string('validation_error_code', 100)->nullable();
            $table->jsonb('mutation_expression')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['schema_version_topic_id', 'key'], 'parameter_definitions_topic_key_unique');
            $table->index(['schema_version_topic_id', 'is_active'], 'parameter_definitions_topic_active_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameter_definitions');
    }
};
