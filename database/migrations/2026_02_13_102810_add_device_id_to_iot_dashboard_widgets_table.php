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
        Schema::table('iot_dashboard_widgets', function (Blueprint $table) {
            $table->foreignId('device_id')
                ->nullable()
                ->after('iot_dashboard_id')
                ->constrained('devices')
                ->nullOnDelete();

            $table->index(['iot_dashboard_id', 'device_id'], 'iot_dashboard_widgets_dashboard_device_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('iot_dashboard_widgets', function (Blueprint $table) {
            $table->dropIndex('iot_dashboard_widgets_dashboard_device_index');
            $table->dropConstrainedForeignId('device_id');
        });
    }
};
