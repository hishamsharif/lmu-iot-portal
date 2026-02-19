<?php

declare(strict_types=1);

use App\Domain\DeviceSchema\Enums\ParameterCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('parameter_definitions', function (Blueprint $table) {
            $table->string('category', 20)->default(ParameterCategory::Measurement->value)->after('type');
            $table->index(
                ['schema_version_topic_id', 'category', 'is_active'],
                'parameter_definitions_topic_category_active_index'
            );
        });

        DB::table('parameter_definitions')
            ->select(['id', 'key', 'validation_rules'])
            ->orderBy('id')
            ->chunkById(250, function ($rows): void {
                foreach ($rows as $row) {
                    $validationRules = $row->validation_rules;

                    if (is_string($validationRules) && $validationRules !== '') {
                        $decoded = json_decode($validationRules, true);
                        $validationRules = is_array($decoded) ? $decoded : null;
                    }

                    $legacyCategory = is_array($validationRules)
                        ? Arr::get($validationRules, 'category')
                        : null;

                    $category = ParameterCategory::fromLegacyCategory(
                        is_string($legacyCategory) ? $legacyCategory : null,
                        is_string($row->key ?? null) ? $row->key : null,
                    );

                    DB::table('parameter_definitions')
                        ->where('id', (int) $row->id)
                        ->update(['category' => $category->value]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parameter_definitions', function (Blueprint $table) {
            $table->dropIndex('parameter_definitions_topic_category_active_index');
            $table->dropColumn('category');
        });
    }
};
