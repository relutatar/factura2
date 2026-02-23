<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnafService
{
    protected string $url = 'https://webservicesp.anaf.ro/api/PlatitorTvaRest/v9/tva';

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
}
