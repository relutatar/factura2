<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Intrare   = 'intrare';
    case Iesire    = 'iesire';
    case Ajustare  = 'ajustare';

    public function label(): string
    {
        return match($this) {
            self::Intrare  => 'Intrare',
            self::Iesire   => 'Ieșire',
            self::Ajustare => 'Ajustare',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Intrare  => 'success',
            self::Iesire   => 'danger',
            self::Ajustare => 'warning',
        };
    }
}
