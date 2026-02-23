<?php

namespace App\Enums;

enum BillingCycle: string
{
    case Lunar       = 'lunar';
    case Trimestrial = 'trimestrial';
    case Anual       = 'anual';
    case Unic        = 'unic';

    public function label(): string
    {
        return match($this) {
            self::Lunar       => 'Lunar',
            self::Trimestrial => 'Trimestrial',
            self::Anual       => 'Anual',
            self::Unic        => 'Unic',
        };
    }
}
