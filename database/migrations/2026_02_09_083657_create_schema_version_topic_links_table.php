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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schema_version_topic_links');
    }
};
