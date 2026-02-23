<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft   = 'draft';
    case Trimisa = 'trimisa';
    case Platita = 'platita';
    case Anulata = 'anulata';

    public function label(): string
    {
        return match($this) {
            self::Draft   => 'Ciornă',
            self::Trimisa => 'Trimisă',
            self::Platita => 'Plătită',
            self::Anulata => 'Anulată',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft   => 'gray',
            self::Trimisa => 'warning',
            self::Platita => 'success',
            self::Anulata => 'danger',
        };
    }
}
