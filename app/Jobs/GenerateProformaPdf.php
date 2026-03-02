<?php

namespace App\Jobs;

use App\Models\Proforma;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateProformaPdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Proforma $proforma) {}

    public function handle(): void
    {
        app(\App\Services\PdfService::class)->generateProforma($this->proforma);
    }
}
