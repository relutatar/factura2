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
        $hasTitleColumn = Schema::hasColumn('contracts', 'title');
        $hasBillingCycleColumn = Schema::hasColumn('contracts', 'billing_cycle');

        if (($hasTitleColumn || $hasBillingCycleColumn) && Schema::hasColumn('contracts', 'additional_attributes')) {
            $columns = ['id', 'additional_attributes'];

            if ($hasTitleColumn) {
                $columns[] = 'title';
            }

            if ($hasBillingCycleColumn) {
                $columns[] = 'billing_cycle';
            }

            DB::table('contracts')
                ->select($columns)
                ->orderBy('id')
                ->chunkById(200, function ($contracts) use ($hasTitleColumn, $hasBillingCycleColumn): void {
                    foreach ($contracts as $contract) {
                        $attributes = $this->decodeJsonArray($contract->additional_attributes ?? null);

                        if ($hasTitleColumn && ! array_key_exists('contract_title', $attributes) && ! blank($contract->title ?? null)) {
                            $attributes['contract_title'] = (string) $contract->title;
                        }

                        if ($hasBillingCycleColumn && ! array_key_exists('billing_cycle', $attributes) && ! blank($contract->billing_cycle ?? null)) {
                            $attributes['billing_cycle'] = (string) $contract->billing_cycle;
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
        }

        $columnsToDrop = [];

        if ($hasTitleColumn) {
            $columnsToDrop[] = 'title';
        }

        if ($hasBillingCycleColumn) {
            $columnsToDrop[] = 'billing_cycle';
        }

        if ($columnsToDrop !== []) {
            Schema::table('contracts', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            if (! Schema::hasColumn('contracts', 'title')) {
                $table->string('title')->nullable()->after('number');
            }

            if (! Schema::hasColumn('contracts', 'billing_cycle')) {
                $table->enum('billing_cycle', ['lunar', 'trimestrial', 'anual', 'unic'])
                    ->default('lunar')
                    ->after('currency');
            }
        });

        if (Schema::hasColumn('contracts', 'additional_attributes')) {
            DB::table('contracts')
                ->select(['id', 'title', 'billing_cycle', 'additional_attributes'])
                ->orderBy('id')
                ->chunkById(200, function ($contracts): void {
                    foreach ($contracts as $contract) {
                        $attributes = $this->decodeJsonArray($contract->additional_attributes ?? null);
                        $updates = [];

                        if (blank($contract->title ?? null) && ! blank($attributes['contract_title'] ?? null)) {
                            $updates['title'] = (string) $attributes['contract_title'];
                        }

                        if (blank($contract->billing_cycle ?? null) && ! blank($attributes['billing_cycle'] ?? null)) {
                            $updates['billing_cycle'] = (string) $attributes['billing_cycle'];
                        }

                        if ($updates === []) {
                            continue;
                        }

                        DB::table('contracts')
                            ->where('id', $contract->id)
                            ->update($updates);
                    }
                });
        }
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
};
