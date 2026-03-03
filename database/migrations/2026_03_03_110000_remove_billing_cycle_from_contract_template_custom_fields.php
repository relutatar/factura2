<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = DB::table('contract_templates')
            ->select(['id', 'custom_fields'])
            ->get();

        foreach ($templates as $template) {
            $fields = json_decode((string) ($template->custom_fields ?? '[]'), true);

            if (! is_array($fields)) {
                continue;
            }

            $filtered = array_values(array_filter($fields, function (mixed $field): bool {
                if (! is_array($field)) {
                    return true;
                }

                $key = strtolower((string) ($field['key'] ?? ''));
                $normalizedKey = preg_replace('/[^a-z0-9_]/', '_', str_replace(['-', ' '], '_', $key));

                return $normalizedKey !== 'billing_cycle';
            }));

            if ($filtered !== $fields) {
                DB::table('contract_templates')
                    ->where('id', $template->id)
                    ->update(['custom_fields' => json_encode($filtered, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    public function down(): void
    {
        // No-op: removed legacy custom field entries are not restored.
    }
};
