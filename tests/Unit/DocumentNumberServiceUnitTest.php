<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\NumberingRange;
use App\Services\DocumentNumberService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentNumberServiceUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_number_uses_padding_format(): void
    {
        $company = $this->createCompany();

        NumberingRange::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'document_type' => 'factura',
            'fiscal_year' => (int) now()->year,
            'series' => 'AB',
            'start_number' => 1,
            'end_number' => 999,
            'next_number' => 7,
            'is_active' => true,
        ]);

        $reservation = app(DocumentNumberService::class)->reserveNextNumber($company, 'factura');

        $this->assertSame('AB-0007', $reservation['full_number']);
    }

    public function test_next_number_transitions_on_boundaries(): void
    {
        $company = $this->createCompany();

        $range = NumberingRange::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'document_type' => 'factura',
            'fiscal_year' => (int) now()->year,
            'series' => 'NOD',
            'start_number' => 1,
            'end_number' => 2,
            'next_number' => 2,
            'is_active' => true,
        ]);

        $reservation = app(DocumentNumberService::class)->reserveNextNumber($company, 'factura');

        $this->assertSame(2, $reservation['number']);
        $this->assertSame(3, $range->fresh()->next_number);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Plaja de numerotare NOD este epuizată.');

        app(DocumentNumberService::class)->reserveNextNumber($company, 'factura');
    }

    private function createCompany(): Company
    {
        return Company::query()->create([
            'name' => 'Companie Test Unit',
            'administrator' => 'Admin Unit',
            'cif' => 'RO' . random_int(10000000, 99999999),
        ]);
    }
}
