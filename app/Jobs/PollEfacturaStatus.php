<?php

namespace App\Jobs;

use App\Models\Invoice;
use App\Services\AnafService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollEfacturaStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(AnafService $anaf): void
    {
        Invoice::withoutGlobalScopes()
            ->where('efactura_status', 'in_prelucrare')
            ->whereNotNull('efactura_id')
            ->with('company')
            ->chunk(50, function ($invoices) use ($anaf): void {
                foreach ($invoices as $invoice) {
                    try {
                        $status = $anaf->pollStatus($invoice->efactura_id, $invoice->company);

                        Invoice::withoutGlobalScopes()
                            ->where('id', $invoice->id)
                            ->update(['efactura_status' => $status]);
                    } catch (\Throwable $e) {
                        Log::warning("PollEfacturaStatus error for invoice {$invoice->id}: " . $e->getMessage());
                    }
                }
            });
    }
}
