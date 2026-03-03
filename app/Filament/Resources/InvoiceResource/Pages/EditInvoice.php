<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $series = (string) ($this->record->series ?? '');
        $number = (int) ($data['number'] ?? $this->record->number ?? 0);

        if ($series !== '' && $number > 0) {
            $fullNumber = $series . '-' . str_pad((string) $number, 4, '0', STR_PAD_LEFT);

            $alreadyExists = Invoice::withoutGlobalScopes()
                ->withTrashed()
                ->where('full_number', $fullNumber)
                ->whereKeyNot($this->record->getKey())
                ->exists();

            if ($alreadyExists) {
                throw ValidationException::withMessages([
                    'data.number' => 'Numărul selectat există deja în această serie. Alegeți alt număr.',
                ]);
            }

            $data['full_number'] = $fullNumber;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        app(InvoiceService::class)->recalculateTotals($this->record);
    }
}
