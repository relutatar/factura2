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
        if (! Schema::hasColumn('contract_templates', 'custom_fields')) {
            Schema::table('contract_templates', function (Blueprint $table): void {
                $table->json('custom_fields')->nullable()->after('content');
            });
        }

        if (! Schema::hasColumn('contracts', 'additional_attributes')) {
            Schema::table('contracts', function (Blueprint $table): void {
                $table->json('additional_attributes')->nullable()->after('status');
            });
        }

        $legacyColumns = $this->legacyColumns();

        if ($legacyColumns !== []) {
            $selectColumns = array_merge(['id', 'additional_attributes'], $legacyColumns);

            DB::table('contracts')
                ->select($selectColumns)
                ->orderBy('id')
                ->chunkById(200, function ($contracts): void {
                    foreach ($contracts as $contract) {
                        $attributes = $this->decodeJsonArray($contract->additional_attributes ?? null);

                        if (! array_key_exists('ddd_frequency', $attributes) && ! empty($contract->ddd_frequency)) {
                            $attributes['ddd_frequency'] = (string) $contract->ddd_frequency;
                        }

                        if (! array_key_exists('ddd_locations', $attributes)) {
                            $locations = $this->decodeJsonValue($contract->ddd_locations ?? null);
                            if ($locations !== null) {
                                $attributes['ddd_locations'] = $locations;
                            }
                        }

                        if (! array_key_exists('paintball_sessions', $attributes) && isset($contract->paintball_sessions)) {
                            $attributes['paintball_sessions'] = (int) $contract->paintball_sessions;
                        }

                        if (! array_key_exists('paintball_players', $attributes) && isset($contract->paintball_players)) {
                            $attributes['paintball_players'] = (int) $contract->paintball_players;
                        }

                        if ($attributes === []) {
                            continue;
                        }

                        DB::table('contracts')
                            ->where('id', $contract->id)
                            ->update([
                                'additional_attributes' => json_encode($attributes, JSON_UNESCAPED_UNICODE),
                            ]);
                    }
                });

            Schema::table('contracts', function (Blueprint $table) use ($legacyColumns): void {
                $table->dropColumn($legacyColumns);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $columnsToAdd = [];

        if (! Schema::hasColumn('contracts', 'ddd_frequency')) {
            $columnsToAdd[] = 'ddd_frequency';
        }

        if (! Schema::hasColumn('contracts', 'ddd_locations')) {
            $columnsToAdd[] = 'ddd_locations';
        }

        if (! Schema::hasColumn('contracts', 'paintball_sessions')) {
            $columnsToAdd[] = 'paintball_sessions';
        }

        if (! Schema::hasColumn('contracts', 'paintball_players')) {
            $columnsToAdd[] = 'paintball_players';
        }

        if ($columnsToAdd !== []) {
            Schema::table('contracts', function (Blueprint $table) use ($columnsToAdd): void {
                if (in_array('ddd_frequency', $columnsToAdd, true)) {
                    $table->string('ddd_frequency')->nullable()->after('status');
                }

                if (in_array('ddd_locations', $columnsToAdd, true)) {
                    $table->json('ddd_locations')->nullable()->after('ddd_frequency');
                }

                if (in_array('paintball_sessions', $columnsToAdd, true)) {
                    $table->unsignedInteger('paintball_sessions')->nullable()->after('ddd_locations');
                }

                if (in_array('paintball_players', $columnsToAdd, true)) {
                    $table->unsignedInteger('paintball_players')->nullable()->after('paintball_sessions');
                }
            });
        }

        if (Schema::hasColumn('contracts', 'additional_attributes')) {
            DB::table('contracts')
                ->select(['id', 'additional_attributes'])
                ->orderBy('id')
                ->chunkById(200, function ($contracts): void {
                    foreach ($contracts as $contract) {
                        $attributes = $this->decodeJsonArray($contract->additional_attributes ?? null);

                        if ($attributes === []) {
                            continue;
                        }

                        $updates = [];

                        if (array_key_exists('ddd_frequency', $attributes)) {
                            $updates['ddd_frequency'] = is_scalar($attributes['ddd_frequency'])
                                ? (string) $attributes['ddd_frequency']
                                : null;
                        }

                        if (array_key_exists('ddd_locations', $attributes)) {
                            $updates['ddd_locations'] = json_encode($attributes['ddd_locations'], JSON_UNESCAPED_UNICODE);
                        }

                        if (array_key_exists('paintball_sessions', $attributes) && is_numeric($attributes['paintball_sessions'])) {
                            $updates['paintball_sessions'] = (int) $attributes['paintball_sessions'];
                        }

                        if (array_key_exists('paintball_players', $attributes) && is_numeric($attributes['paintball_players'])) {
                            $updates['paintball_players'] = (int) $attributes['paintball_players'];
                        }

                        if ($updates === []) {
                            continue;
                        }

                        DB::table('contracts')
                            ->where('id', $contract->id)
                            ->update($updates);
                    }
                });

            Schema::table('contracts', function (Blueprint $table): void {
                $table->dropColumn('additional_attributes');
            });
        }

        if (Schema::hasColumn('contract_templates', 'custom_fields')) {
            Schema::table('contract_templates', function (Blueprint $table): void {
                $table->dropColumn('custom_fields');
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function legacyColumns(): array
    {
        $columns = [];

        foreach (['ddd_frequency', 'ddd_locations', 'paintball_sessions', 'paintball_players'] as $column) {
            if (Schema::hasColumn('contracts', $column)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function decodeJsonValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value)) {
            return $value;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $trimmed;
    }
};
