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
            $table->index(['device_id', 'recorded_at'], 'device_telemetry_logs_device_recorded_index');
            $table->index(
                ['device_id', 'processing_state', 'recorded_at'],
                'device_telemetry_logs_device_state_recorded_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_telemetry_logs', function (Blueprint $table) {
            $table->dropIndex('device_telemetry_logs_device_recorded_index');
            $table->dropIndex('device_telemetry_logs_device_state_recorded_index');
        });
    }
};
