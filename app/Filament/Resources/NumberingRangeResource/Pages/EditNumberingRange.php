<?php

namespace App\Filament\Resources\NumberingRangeResource\Pages;

use App\Filament\Resources\NumberingRangeResource;
use App\Services\DocumentNumberService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditNumberingRange extends EditRecord
{
    protected static string $resource = NumberingRangeResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $maxUsedNumber = app(DocumentNumberService::class)->getMaxUsedNumberInRange($this->record);

        if ($maxUsedNumber !== null) {
            $startNumber = (int) ($data['start_number'] ?? $this->record->start_number);
            $endNumber = (int) ($data['end_number'] ?? $this->record->end_number);

            if ($startNumber > $maxUsedNumber) {
                throw ValidationException::withMessages([
                    'data.start_number' => 'Numărul de început nu poate depăși ultimul număr deja folosit (' . $maxUsedNumber . ').',
                ]);
            }

            if ($endNumber < $maxUsedNumber) {
                throw ValidationException::withMessages([
                    'data.end_number' => 'Numărul final nu poate fi mai mic decât ultimul număr deja folosit (' . $maxUsedNumber . ').',
                ]);
            }
        }

        return $data;
    }
}
