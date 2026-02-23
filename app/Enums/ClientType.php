<?php

namespace App\Enums;

enum ClientType: string
{
    case PersoanaFizica   = 'persoana_fizica';
    case PersoanaJuridica = 'persoana_juridica';

    public function label(): string
    {
        return match($this) {
            self::PersoanaFizica   => 'Persoană Fizică',
            self::PersoanaJuridica => 'Persoană Juridică',
        };
    }
}
