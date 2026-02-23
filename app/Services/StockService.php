<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;

class StockService
{
    /**
     * Record an incoming stock movement (purchase/receipt).
     */
    public function recordEntry(
        Product $product,
        float $quantity,
        float $unitPrice,
        ?string $notes = null
    ): StockMovement {
        return StockMovement::create([
            'product_id' => $product->id,
            'type'       => StockMovementType::Intrare,
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
            'notes'      => $notes,
            'moved_at'   => now(),
        ]);
    }

    /**
     * Deduct stock for all lines of a finalized invoice.
     * Called when invoice status transitions to 'trimisa' or 'platita'.
     */
    public function deductForInvoice(Invoice $invoice): void
    {
        foreach ($invoice->lines as $line) {
            if (! $line->product_id) {
                continue;
            }

            StockMovement::create([
                'product_id' => $line->product_id,
                'invoice_id' => $invoice->id,
                'type'       => StockMovementType::Iesire,
                'quantity'   => $line->quantity,
                'unit_price' => $line->unit_price,
                'notes'      => "Factură {$invoice->full_number}",
                'moved_at'   => now(),
            ]);
        }
    }

    /**
     * Return all products for the active company that are below minimum stock.
     */
    public function getLowStockProducts(): Collection
    {
        return Product::whereColumn('stock_quantity', '<=', 'stock_minimum')
            ->where('is_active', true)
            ->orderByRaw('stock_quantity - stock_minimum ASC')
            ->get();
    }
}
