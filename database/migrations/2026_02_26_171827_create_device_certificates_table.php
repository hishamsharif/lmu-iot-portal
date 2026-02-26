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
        Schema::create('device_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('device_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('serial_number', 191)->index();
            $table->text('subject_dn');
            $table->string('fingerprint_sha256', 128)->unique();
            $table->text('certificate_pem');
            $table->text('private_key_encrypted');
            $table->timestamp('issued_at');
            $table->timestamp('not_before');
            $table->timestamp('not_after');
            $table->timestamp('revoked_at')->nullable()->index();
            $table->string('revocation_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['device_id', 'not_after'], 'device_certificates_device_not_after_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('device_certificates');
    }
};
