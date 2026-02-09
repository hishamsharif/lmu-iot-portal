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
        Schema::table('parameter_definitions', function (Blueprint $table) {
            $table->jsonb('control_ui')->nullable()->after('validation_rules');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parameter_definitions', function (Blueprint $table) {
            $table->dropColumn('control_ui');
        });
    }
};
