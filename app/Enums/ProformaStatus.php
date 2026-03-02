<?php

namespace App\Enums;

enum ProformaStatus: string
{
    case Draft      = 'draft';
    case Trimisa    = 'trimisa';
    case Convertita = 'convertita';
    case Anulata    = 'anulata';

    public function label(): string
    {
        return match($this) {
            self::Draft      => 'Ciornă',
            self::Trimisa    => 'Trimisă',
            self::Convertita => 'Convertită',
            self::Anulata    => 'Anulată',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Draft      => 'gray',
            self::Trimisa    => 'warning',
            self::Convertita => 'success',
            self::Anulata    => 'danger',
        };
    }
}
