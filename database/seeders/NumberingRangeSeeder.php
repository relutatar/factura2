<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\NumberingRange;
use Illuminate\Database\Seeder;

class NumberingRangeSeeder extends Seeder
{
    public function run(): void
    {
        $year = (int) now()->year;

        $rangesByCif = [
            'RO27864858' => 'NOD',
            '36408451' => 'PBM',
        ];

        $companies = Company::withoutGlobalScopes()->get(['id', 'cif']);

        foreach ($companies as $company) {
            $series = $rangesByCif[$company->cif] ?? 'F';

            NumberingRange::updateOrCreate(
                [
                    'company_id'      => $company->id,
                    'document_type'   => 'factura',
                    'fiscal_year'     => $year,
                    'series'          => $series,
                    'work_point_code' => null,
                ],
                [
                    'start_number' => 1,
                    'end_number'   => 999999,
                    'next_number'  => 1,
                    'is_active'    => true,
                ]
            );
        }
    }
}
