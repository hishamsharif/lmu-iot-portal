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
        Schema::create('report_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 30)->default('queued');
            $table->string('format', 20)->default('csv');
            $table->string('grouping', 20)->nullable();
            $table->jsonb('parameter_keys')->nullable();
            $table->timestamp('from_at');
            $table->timestamp('until_at');
            $table->string('timezone', 64)->default('UTC');
            $table->jsonb('payload')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->string('storage_disk', 50)->nullable();
            $table->string('storage_path')->nullable();
            $table->string('file_name', 255)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status', 'created_at'], 'report_runs_org_status_created_index');
            $table->index(['device_id', 'created_at'], 'report_runs_device_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_runs');
    }
};
