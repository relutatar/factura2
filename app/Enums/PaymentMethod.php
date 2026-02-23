<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Numerar    = 'numerar';
    case OrdinPlata = 'ordin_plata';
    case Card       = 'card';
    case Compensare = 'compensare';

    public function label(): string
    {
        return match($this) {
            self::Numerar    => 'Numerar',
            self::OrdinPlata => 'Ordin de plată',
            self::Card       => 'Card bancar',
            self::Compensare => 'Compensare',
        };
    }
}
