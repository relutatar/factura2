<?php

namespace App\Enums;

enum CompanyModule: string
{
    case ActeAditionale = 'acte_aditionale';
    case ProceseVerbale = 'procese_verbale';
    case Stocuri        = 'stocuri';
    case Efactura       = 'efactura';
    case BonuriFiscale  = 'bonuri_fiscale';

    public function label(): string
    {
        return match($this) {
            self::ActeAditionale => 'Acte adiționale și Anexe',
            self::ProceseVerbale => 'Procese verbale de lucrări',
            self::Stocuri        => 'Gestiunea produselor și stocurilor',
            self::Efactura       => 'E-Factura (ANAF)',
            self::BonuriFiscale  => 'Bonuri Fiscale',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(
            fn (self $case) => [$case->value => $case->label()]
        )->all();
    }
}
