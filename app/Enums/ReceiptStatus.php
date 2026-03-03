<?php

namespace App\Enums;

enum ReceiptStatus: string
{
    case Emisa = 'emisa';
    case Anulata = 'anulata';

    public function label(): string
    {
        return match ($this) {
            self::Emisa => 'Emisă',
            self::Anulata => 'Anulată',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Emisa => 'success',
            self::Anulata => 'danger',
        };
    }
}
