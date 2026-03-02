<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DecisionAuditLog extends Model
{
    protected $fillable = [
        'company_id',
        'decision_id',
        'decision_template_id',
        'actor_id',
        'event',
        'result',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    /**
     * @param  array<string, mixed>  $data
     */
    public static function log(string $event, array $data = []): void
    {
        static::query()->create([
            'company_id' => $data['company_id'] ?? null,
            'decision_id' => $data['decision_id'] ?? null,
            'decision_template_id' => $data['decision_template_id'] ?? null,
            'actor_id' => auth()->id(),
            'event' => $event,
            'result' => (string) ($data['result'] ?? 'success'),
            'payload' => $data['payload'] ?? null,
        ]);
    }
}
