<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Invoice;

class InvoiceService
{
    /**
     * Create a draft invoice from a contract.
     */
    public function createFromContract(Contract $contract): Invoice
    {
        return Invoice::create([
            'company_id'  => $contract->company_id,
            'client_id'   => $contract->client_id,
            'contract_id' => $contract->id,
            'type'        => 'factura',
            'status'      => 'draft',
            'issue_date'  => now()->toDateString(),
            'due_date'    => now()->addDays(30)->toDateString(),
        ]);
    }
}
