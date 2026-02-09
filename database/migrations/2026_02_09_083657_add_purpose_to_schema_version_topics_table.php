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
        Schema::table('schema_version_topics', function (Blueprint $table) {
            $table->string('purpose', 50)->nullable()->after('direction');
            $table->index(['direction', 'purpose'], 'schema_version_topics_direction_purpose_index');
        });

        DB::table('schema_version_topics')
            ->select(['id', 'direction', 'suffix', 'retain'])
            ->orderBy('id')
            ->chunkById(200, function ($topics): void {
                foreach ($topics as $topic) {
                    $direction = (string) ($topic->direction ?? '');
                    $suffix = strtolower((string) ($topic->suffix ?? ''));
                    $retain = (bool) ($topic->retain ?? false);

                    $purpose = match (true) {
                        $direction === 'subscribe' => 'command',
                        str_contains($suffix, 'ack') => 'ack',
                        $retain => 'state',
                        in_array($suffix, ['state', 'status'], true) => 'state',
                        default => 'telemetry',
                    };

                    DB::table('schema_version_topics')
                        ->where('id', $topic->id)
                        ->update(['purpose' => $purpose]);
                }
            });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                ALTER TABLE schema_version_topics
                ADD CONSTRAINT schema_version_topics_purpose_check
                CHECK (purpose IN ('command', 'state', 'telemetry', 'event', 'ack'))
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE schema_version_topics DROP CONSTRAINT IF EXISTS schema_version_topics_purpose_check');
        }

        Schema::table('schema_version_topics', function (Blueprint $table) {
            $table->dropIndex('schema_version_topics_direction_purpose_index');
            $table->dropColumn('purpose');
        });
    }
};
