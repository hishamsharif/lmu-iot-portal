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
        Schema::table('device_schema_versions', function (Blueprint $table) {
            $table->string('firmware_filename')->nullable()->after('notes');
            $table->longText('firmware_template')->nullable()->after('firmware_filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('device_schema_versions', function (Blueprint $table) {
            $table->dropColumn(['firmware_filename', 'firmware_template']);
        });
    }
};
