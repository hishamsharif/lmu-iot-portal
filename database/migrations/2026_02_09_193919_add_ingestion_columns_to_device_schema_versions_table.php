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
        Schema::table('device_schema_versions', function (Blueprint $table) {
            $table->jsonb('ingestion_config')->nullable()->after('firmware_template');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_schema_versions', function (Blueprint $table) {
            $table->dropColumn('ingestion_config');
        });
    }
};
