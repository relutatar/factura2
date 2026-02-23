<?php

namespace App\Enums;

enum ContractStatus: string
{
    case Activ     = 'activ';
    case Suspendat = 'suspendat';
    case Expirat   = 'expirat';
    case Reziliat  = 'reziliat';

    public function label(): string
    {
        return match($this) {
            self::Activ     => 'Activ',
            self::Suspendat => 'Suspendat',
            self::Expirat   => 'Expirat',
            self::Reziliat  => 'Reziliat',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Activ     => 'success',
            self::Suspendat => 'warning',
            self::Expirat   => 'danger',
            self::Reziliat  => 'gray',
        };
    }
}
