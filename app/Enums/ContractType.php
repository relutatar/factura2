<?php

namespace App\Enums;

enum ContractType: string
{
    case MentenantaDDD      = 'mentenanta_ddd';
    case EvenimentPaintball = 'eveniment_paintball';

    public function label(): string
    {
        return match($this) {
            self::MentenantaDDD      => 'Mentenanță DDD',
            self::EvenimentPaintball => 'Eveniment Paintball',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::MentenantaDDD      => 'info',
            self::EvenimentPaintball => 'warning',
        };
    }
}
