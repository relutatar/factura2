<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case ViramentBancar = 'virament_bancar';
    case Numerar        = 'numerar';

    public function label(): string
    {
        return match($this) {
            self::ViramentBancar => 'Virament bancar',
            self::Numerar        => 'Numerar',
        };
    }
}
