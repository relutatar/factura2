<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Factura  = 'factura';
    case Proforma = 'proforma';
    case Chitanta = 'chitanta';
    case Aviz     = 'aviz';

    public function label(): string
    {
        return match($this) {
            self::Factura  => 'Factură',
            self::Proforma => 'Proformă',
            self::Chitanta => 'Chitanță',
            self::Aviz     => 'Aviz',
        };
    }

    public function prefix(): string
    {
        return match($this) {
            self::Factura  => 'F',
            self::Proforma => 'P',
            self::Chitanta => 'C',
            self::Aviz     => 'A',
        };
    }
}
