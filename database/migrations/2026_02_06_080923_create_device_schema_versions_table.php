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
        Schema::create('device_schema_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_schema_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 50)->default('draft');
            $table->text('notes')->nullable();
            $table->longText('firmware_template')->nullable();
            $table->timestamps();

            $table->unique(['device_schema_id', 'version'], 'device_schema_versions_schema_version_unique');
            $table->index(['device_schema_id', 'status'], 'device_schema_versions_schema_status_index');
        });

        DB::statement("CREATE UNIQUE INDEX device_schema_versions_active_unique ON device_schema_versions (device_schema_id) WHERE status = 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS device_schema_versions_active_unique');
        Schema::dropIfExists('device_schema_versions');
    }
};
