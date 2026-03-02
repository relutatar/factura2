<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 60px 100px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f0f0f0; font-weight: bold; }
        .no-border td { border: none; }
        .right { text-align: right; }
        .center { text-align: center; }
        .total-row td { font-weight: bold; background: #f5f5f5; }
        h2 { margin: 0 0 4px 0; font-size: 18px; }
        h3 { font-size: 13px; margin: 16px 0 6px 0; }
        .label { color: #666; }
        .disclaimer { font-size: 10px; color: #666; font-style: italic; margin-top: 20px; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>

{{-- Header: company + document title --}}
<table class="no-border" style="margin-bottom: 24px;">
    <tr>
        <td style="width:55%; vertical-align:top;">
            @if($proforma->company->logo)
                <img src="{{ storage_path('app/public/' . $proforma->company->logo) }}" height="55" style="margin-bottom:6px;"><br>
            @endif
            <strong>{{ $proforma->company->name }}</strong><br>
            <span class="label">CIF:</span> {{ $proforma->company->cif }}<br>
            <span class="label">Reg. Com.:</span> {{ $proforma->company->reg_com }}<br>
            {{ $proforma->company->address }}, {{ $proforma->company->city }}<br>
            <span class="label">IBAN:</span> {{ $proforma->company->iban }}
            @if($proforma->company->bank)
                &nbsp;|&nbsp; {{ $proforma->company->bank }}
            @endif
        </td>
        <td style="text-align:right; vertical-align:top;">
            <h2>FACTURĂ PROFORMĂ</h2>
            <strong>Nr: {{ $proforma->full_number ?? 'Ciornă' }}</strong><br>
            <span class="label">Data emiterii:</span> {{ $proforma->issue_date->format('d.m.Y') }}<br>
            @if($proforma->valid_until)
                <span class="label">Valabilă până la:</span> {{ $proforma->valid_until->format('d.m.Y') }}<br>
            @endif
        </td>
    </tr>
</table>

{{-- Client details --}}
<h3>Beneficiar:</h3>
<table class="no-border" style="margin-bottom: 20px;">
    <tr>
        <td style="width:55%;">
            <strong>{{ $proforma->client->name }}</strong><br>
            @if($proforma->client->cif)
                <span class="label">CIF:</span> {{ $proforma->client->cif }}<br>
            @elseif($proforma->client->cnp)
                <span class="label">CNP:</span> {{ $proforma->client->cnp }}<br>
            @endif
            @if($proforma->client->reg_com)
                <span class="label">Reg. Com.:</span> {{ $proforma->client->reg_com }}<br>
            @endif
            {{ $proforma->client->address ?? '' }}{{ $proforma->client->city ? ', ' . $proforma->client->city : '' }}
        </td>
        <td>
            @if($proforma->contract?->number)
                <span class="label">Contract:</span> {{ $proforma->contract->number }}<br>
            @endif
        </td>
    </tr>
</table>

{{-- Proforma lines --}}
<table>
    <thead>
        <tr>
            <th class="center" style="width:4%">#</th>
            <th style="width:36%">Descriere</th>
            <th class="center" style="width:6%">UM</th>
            <th class="right" style="width:8%">Cant.</th>
            <th class="right" style="width:10%">Preț/UM</th>
            <th class="right" style="width:8%">TVA %</th>
            <th class="right" style="width:14%">Valoare fără TVA</th>
            <th class="right" style="width:14%">Total cu TVA</th>
        </tr>
    </thead>
    <tbody>
        @foreach($proforma->lines as $i => $line)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $line->description }}</td>
            <td class="center">{{ $line->unit }}</td>
            <td class="right">{{ number_format((float)$line->quantity, 2, ',', '.') }}</td>
            <td class="right">{{ number_format((float)$line->unit_price, 2, ',', '.') }}</td>
            <td class="right">{{ rtrim(rtrim(number_format((float)$line->vatRate->value, 2, '.', ''), '0'), '.') }}%</td>
            <td class="right">{{ number_format((float)$line->line_total, 2, ',', '.') }}</td>
            <td class="right">{{ number_format((float)$line->total_with_vat, 2, ',', '.') }}</td>
        </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="6" class="right">Subtotal:</td>
            <td colspan="2" class="right">{{ number_format((float)$proforma->subtotal, 2, ',', '.') }} RON</td>
        </tr>
        <tr>
            <td colspan="6" class="right">TVA:</td>
            <td colspan="2" class="right">{{ number_format((float)$proforma->vat_total, 2, ',', '.') }} RON</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" class="right">TOTAL:</td>
            <td colspan="2" class="right">{{ number_format((float)$proforma->total, 2, ',', '.') }} RON</td>
        </tr>
    </tfoot>
</table>

@if($proforma->notes)
    <p style="margin-top:16px;"><strong>Observații:</strong> {{ $proforma->notes }}</p>
@endif

<table class="no-border" style="margin-top: 40px;">
    <tr>
        <td class="center" style="width:50%;">
            <strong>Emitent</strong><br>
            {{ $proforma->company->name }}<br><br><br>
            ____________________________<br>
            Semnătură și ștampilă
        </td>
        <td class="center" style="width:50%;">
            <strong>Beneficiar</strong><br>
            {{ $proforma->client->name }}<br><br><br>
            ____________________________<br>
            Semnătură
        </td>
    </tr>
</table>

<p class="disclaimer">
    Prezentul document nu reprezintă o factură fiscală și nu este supus înregistrării contabile.
    Factura fiscală va fi emisă după confirmarea plății.
</p>

</body>
</html>
