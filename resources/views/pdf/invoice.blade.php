<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 20px; }
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
    </style>
</head>
<body>

{{-- Header: company + document title --}}
<table class="no-border" style="margin-bottom: 24px;">
    <tr>
        <td style="width:55%; vertical-align:top;">
            @if($invoice->company->logo)
                <img src="{{ storage_path('app/public/' . $invoice->company->logo) }}" height="55" style="margin-bottom:6px;"><br>
            @endif
            <strong>{{ $invoice->company->name }}</strong><br>
            <span class="label">CIF:</span> {{ $invoice->company->cif }}<br>
            <span class="label">Reg. Com.:</span> {{ $invoice->company->reg_com }}<br>
            {{ $invoice->company->address }}, {{ $invoice->company->city }}<br>
            <span class="label">IBAN:</span> {{ $invoice->company->iban }}
            @if($invoice->company->bank)
                &nbsp;|&nbsp; {{ $invoice->company->bank }}
            @endif
        </td>
        <td style="text-align:right; vertical-align:top;">
            <h2>{{ strtoupper($invoice->type->label()) }}</h2>
            <strong>Nr: {{ $invoice->full_number }}</strong><br>
            <span class="label">Data emiterii:</span> {{ $invoice->issue_date->format('d.m.Y') }}<br>
            <span class="label">Scadență:</span> {{ $invoice->due_date?->format('d.m.Y') ?? '—' }}<br>
            @if($invoice->delivery_date)
                <span class="label">Data livrării:</span> {{ $invoice->delivery_date->format('d.m.Y') }}<br>
            @endif
        </td>
    </tr>
</table>

{{-- Client details --}}
<h3>Cumpărător / Beneficiar:</h3>
<table class="no-border" style="margin-bottom: 20px;">
    <tr>
        <td style="width:55%;">
            <strong>{{ $invoice->client->name }}</strong><br>
            @if($invoice->client->cif)
                <span class="label">CIF:</span> {{ $invoice->client->cif }}<br>
            @elseif($invoice->client->cnp)
                <span class="label">CNP:</span> {{ $invoice->client->cnp }}<br>
            @endif
            @if($invoice->client->reg_com)
                <span class="label">Reg. Com.:</span> {{ $invoice->client->reg_com }}<br>
            @endif
            {{ $invoice->client->address }}, {{ $invoice->client->city }}
        </td>
        <td>
            @if($invoice->contract)
                <span class="label">Contract:</span> {{ $invoice->contract->number }}<br>
            @endif
            <span class="label">Modalitate plată:</span> {{ $invoice->payment_method->label() }}<br>
            @if($invoice->payment_reference)
                <span class="label">Referință plată:</span> {{ $invoice->payment_reference }}<br>
            @endif
        </td>
    </tr>
</table>

{{-- Invoice lines --}}
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
        @foreach($invoice->lines as $i => $line)
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
            <td colspan="2" class="right">{{ number_format((float)$invoice->subtotal, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
        <tr>
            <td colspan="6" class="right">TVA:</td>
            <td colspan="2" class="right">{{ number_format((float)$invoice->vat_total, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
        <tr class="total-row">
            <td colspan="6" class="right">TOTAL DE PLATĂ:</td>
            <td colspan="2" class="right">{{ number_format((float)$invoice->total, 2, ',', '.') }} {{ $invoice->currency }}</td>
        </tr>
    </tfoot>
</table>

@if($invoice->notes)
    <p style="margin-top:16px;"><strong>Observații:</strong> {{ $invoice->notes }}</p>
@endif

<table class="no-border" style="margin-top: 40px;">
    <tr>
        <td class="center" style="width:50%;">
            <strong>Furnizor</strong><br>
            {{ $invoice->company->name }}<br><br><br>
            ____________________________<br>
            Semnătură și ștampilă
        </td>
        <td class="center" style="width:50%;">
            <strong>Cumpărător</strong><br>
            {{ $invoice->client->name }}<br><br><br>
            ____________________________<br>
            Semnătură și ștampilă
        </td>
    </tr>
</table>

</body>
</html>
