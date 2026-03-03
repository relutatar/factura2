<?php

namespace App\Enums;

enum ContractAmendmentStatus: string
{
    case Draft = 'draft';
    case Semnat = 'semnat';
    case Anulat = 'anulat';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Ciornă',
            self::Semnat => 'Semnat',
            self::Anulat => 'Anulat',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Semnat => 'success',
            self::Anulat => 'danger',
        };
    }
}
