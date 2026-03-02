<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Ciornă',
            self::Issued => 'Emisă',
            self::Cancelled => 'Anulată',
            self::Archived => 'Arhivată',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Issued => 'success',
            self::Cancelled => 'danger',
            self::Archived => 'warning',
        };
    }
}
