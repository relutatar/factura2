<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnafService
{
    protected string $url = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

    private const ANAF_BASE_PROD = 'https://api.anaf.ro/prod/FCTEL/rest';
    private const ANAF_BASE_TEST = 'https://api.anaf.ro/test/FCTEL/rest';

    private function baseUrl(Company $company): string
    {
        return $company->efactura_test_mode ? self::ANAF_BASE_TEST : self::ANAF_BASE_PROD;
    }

    /**
     * Lookup a CIF via ANAF public API v9.
     * Returns a normalized flat array, or null if not found.
     *
     * @param  string|int  $cif
     * @return array|null
     */
    public function lookupCif(string|int $cif): ?array
    {
        $cif = preg_replace('/\D/', '', (string) $cif);
        if (empty($cif)) {
            return null;
        }

        $cacheKey = 'anaf_cif_' . $cif;

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($cif) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->post($this->url, [
                        ['cui' => (int) $cif, 'data' => now()->format('Y-m-d')],
                    ]);

                if (! $response->successful()) {
                    Log::warning('ANAF lookup failed', ['cif' => $cif, 'status' => $response->status()]);
                    return null;
                }

                $body  = $response->json();
                $found = $body['found'][0] ?? null;

                if (! $found) {
                    return null;
                }

                $dg  = $found['date_generale'] ?? [];
                $adr = $found['adresa_sediu_social'] ?? [];

                return [
                    'denumire'      => $dg['denumire']  ?? null,
                    'adresa'        => $dg['adresa']    ?? null,
                    'nrRegCom'      => $dg['nrRegCom']  ?? null,
                    'telefon'       => $dg['telefon']   ?? null,
                    'localitate'    => $adr['sdenumire_Localitate'] ?? null,
                    'judet'         => $adr['sdenumire_Judet']      ?? null,
                    'scpTVA'        => $found['inregistrare_scop_Tva']['scpTVA']    ?? false,
                    'statusInactiv' => $found['stare_inactiv']['statusInactivi']    ?? false,
                ];
            } catch (\Throwable $e) {
                Log::error('ANAF lookup exception', ['cif' => $cif, 'error' => $e->getMessage()]);
                return null;
            }
        });
    }

    /**
     * Generate UBL 2.1 XML for the given invoice.
     */
    public function generateXml(Invoice $invoice): string
    {
        $invoice->loadMissing(['company', 'client', 'lines.product', 'lines.vatRate']);

        $issueDateStr = $invoice->issue_date->format('Y-m-d');
        $dueDateStr   = $invoice->due_date?->format('Y-m-d') ?? $issueDateStr;
        $currency     = 'RON';

        $linesXml = '';
        foreach ($invoice->lines as $i => $line) {
            $vatPercent = $line->vatRate?->value ?? 0;
            $linesXml .= <<<XML

    <cac:InvoiceLine>
        <cbc:ID>{$i}</cbc:ID>
        <cbc:InvoicedQuantity unitCode="{$line->unit}">{$line->quantity}</cbc:InvoicedQuantity>
        <cbc:LineExtensionAmount currencyID="{$currency}">{$line->line_total}</cbc:LineExtensionAmount>
        <cac:Item>
            <cbc:Name>{$line->description}</cbc:Name>
            <cac:ClassifiedTaxCategory>
                <cbc:ID>S</cbc:ID>
                <cbc:Percent>{$vatPercent}</cbc:Percent>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:ClassifiedTaxCategory>
        </cac:Item>
        <cac:Price>
            <cbc:PriceAmount currencyID="{$currency}">{$line->unit_price}</cbc:PriceAmount>
        </cac:Price>
    </cac:InvoiceLine>
XML;
        }

        $companyName = htmlspecialchars($invoice->company->name ?? '');
        $companyCif  = preg_replace('/\D/', '', $invoice->company->cif ?? '');
        $clientName  = htmlspecialchars($invoice->client->name ?? '');
        $clientCif   = preg_replace('/\D/', '', $invoice->client->cif ?? '');

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cbc:UBLVersionID>2.1</cbc:UBLVersionID>
    <cbc:ID>{$invoice->full_number}</cbc:ID>
    <cbc:IssueDate>{$issueDateStr}</cbc:IssueDate>
    <cbc:DueDate>{$dueDateStr}</cbc:DueDate>
    <cbc:InvoiceTypeCode>380</cbc:InvoiceTypeCode>
    <cbc:DocumentCurrencyCode>{$currency}</cbc:DocumentCurrencyCode>

    <cac:AccountingSupplierParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$companyName}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>RO{$companyCif}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>

    <cac:AccountingCustomerParty>
        <cac:Party>
            <cac:PartyName><cbc:Name>{$clientName}</cbc:Name></cac:PartyName>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>RO{$clientCif}</cbc:CompanyID>
                <cac:TaxScheme><cbc:ID>VAT</cbc:ID></cac:TaxScheme>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingCustomerParty>

    <cac:TaxTotal>
        <cbc:TaxAmount currencyID="{$currency}">{$invoice->vat_total}</cbc:TaxAmount>
    </cac:TaxTotal>

    <cac:LegalMonetaryTotal>
        <cbc:LineExtensionAmount currencyID="{$currency}">{$invoice->subtotal}</cbc:LineExtensionAmount>
        <cbc:TaxExclusiveAmount currencyID="{$currency}">{$invoice->subtotal}</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="{$currency}">{$invoice->total}</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="{$currency}">{$invoice->total}</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
    {$linesXml}
</Invoice>
XML;
    }

    /**
     * Upload UBL XML to ANAF SPV.
     * Returns the upload ID (used for polling).
     *
     * @throws \RuntimeException
     */
    public function uploadInvoice(Invoice $invoice, Company $company): string
    {
        $xml  = $this->generateXml($invoice);
        $base = $this->baseUrl($company);

        $response = Http::withOptions(['cert' => [
            storage_path('app/' . $company->efactura_certificate_path),
            $company->efactura_certificate_password,
        ]])->withBody($xml, 'application/xml')
            ->post("{$base}/upload?standard=UBL&cif={$company->efactura_cif}");

        if ($response->failed() || empty($response->json('index_incarcare'))) {
            Log::error('ANAF upload failed', ['response' => $response->body(), 'invoice' => $invoice->id]);
            throw new \RuntimeException('Eroare la trimiterea la ANAF: ' . $response->body());
        }

        return (string) $response->json('index_incarcare');
    }

    /**
     * Poll ANAF for the result of a previously uploaded invoice.
     * Returns one of: 'ok', 'nok', 'in_prelucrare', 'necunoscut'
     *
     * @throws \RuntimeException
     */
    public function pollStatus(string $uploadId, Company $company): string
    {
        $base = $this->baseUrl($company);

        $response = Http::withOptions(['cert' => [
            storage_path('app/' . $company->efactura_certificate_path),
            $company->efactura_certificate_password,
        ]])->get("{$base}/stareMesaj", ['id_incarcare' => $uploadId]);

        if ($response->failed()) {
            throw new \RuntimeException('Eroare la interogarea ANAF: ' . $response->body());
        }

        return match ($response->json('stare')) {
            'ok'            => 'ok',
            'nok'           => 'nok',
            'in prelucrare' => 'in_prelucrare',
            default         => 'necunoscut',
        };
    }
}
