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

        $seriesByCompany = [
            'NOD CONSULTING' => [
                'factura' => 'NOD',
                'proforma' => 'PNOD',
                'chitanta' => 'CHNOD',
                'aviz' => 'AVNOD',
            ],
            'PAINTBALL MUREȘ' => [
                'factura' => 'PBM',
                'proforma' => 'PPBM',
                'chitanta' => 'CHPBM',
                'aviz' => 'AVPBM',
            ],
            'PAINTBALL MURES' => [
                'factura' => 'PBM',
                'proforma' => 'PPBM',
                'chitanta' => 'CHPBM',
                'aviz' => 'AVPBM',
            ],
        ];

        $companies = Company::withoutGlobalScopes()->get(['id', 'name']);

        foreach ($companies as $company) {
            $seriesMap = $seriesByCompany[$company->name] ?? [
                'factura' => 'F',
                'proforma' => 'PF',
                'chitanta' => 'CH',
                'aviz' => 'AV',
            ];

            foreach ($seriesMap as $documentType => $series) {
                NumberingRange::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'document_type' => $documentType,
                        'fiscal_year' => $year,
                        'series' => $series,
                        'work_point_code' => null,
                    ],
                    [
                        'start_number' => 1,
                        'end_number' => 999999,
                        'next_number' => 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
