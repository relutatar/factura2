<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\AnafService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitEfactura implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var int Number of times the job may be attempted. */
    public int $tries = 3;

    /** @var int Number of seconds to wait before retrying the job. */
    public int $backoff = 60;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Company $company,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AnafService $anaf): void
    {
        try {
            $uploadId = $anaf->uploadInvoice($this->invoice, $this->company);

            Invoice::withoutGlobalScopes()
                ->where('id', $this->invoice->id)
                ->update([
                    'efactura_id'     => $uploadId,
                    'efactura_status' => 'in_prelucrare',
                ]);
        } catch (\Throwable $e) {
            Log::error("SubmitEfactura failed for invoice {$this->invoice->id}: " . $e->getMessage());
            $this->fail($e);
        }
    }
}
