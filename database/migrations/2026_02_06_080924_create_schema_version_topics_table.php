<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('schema_version_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_schema_version_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->string('label', 255);
            $table->string('direction', 50);
            $table->string('purpose', 50)->nullable();
            $table->string('suffix', 255);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('qos')->default(1);
            $table->boolean('retain')->default(false);
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamps();

            $table->unique(['device_schema_version_id', 'key'], 'schema_version_topics_version_key_unique');
            $table->unique(['device_schema_version_id', 'suffix'], 'schema_version_topics_version_suffix_unique');
            $table->index(['direction', 'purpose'], 'schema_version_topics_direction_purpose_index');
        });

        Schema::create('schema_version_topic_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_schema_version_topic_id')
                ->constrained('schema_version_topics')
                ->cascadeOnDelete();
            $table->foreignId('to_schema_version_topic_id')
                ->constrained('schema_version_topics')
                ->cascadeOnDelete();
            $table->string('link_type', 50);
            $table->timestamps();

            $table->unique(
                ['from_schema_version_topic_id', 'to_schema_version_topic_id', 'link_type'],
                'schema_version_topic_links_unique'
            );
            $table->index('from_schema_version_topic_id', 'schema_version_topic_links_from_index');
            $table->index('to_schema_version_topic_id', 'schema_version_topic_links_to_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE schema_version_topics
                ADD CONSTRAINT schema_version_topics_purpose_check
                CHECK (purpose IS NULL OR purpose IN ('command', 'state', 'telemetry', 'event', 'ack'))
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schema_version_topics DROP CONSTRAINT IF EXISTS schema_version_topics_purpose_check');
        }

        Schema::dropIfExists('schema_version_topic_links');
        Schema::dropIfExists('schema_version_topics');
    }
};
