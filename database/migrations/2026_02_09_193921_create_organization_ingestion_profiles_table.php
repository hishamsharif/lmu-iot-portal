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
        Schema::create('organization_ingestion_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('raw_retention_days')->default(90);
            $table->unsignedInteger('debug_log_retention_days')->default(14);
            $table->unsignedInteger('soft_msgs_per_minute')->default(60_000);
            $table->unsignedInteger('soft_storage_mb_per_day')->default(2_048);
            $table->string('tier', 50)->default('standard');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organization_ingestion_profiles');
    }
};
