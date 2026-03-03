<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 16px; text-align: center; text-transform: uppercase; margin-bottom: 6px; }
        .number { text-align: center; margin-bottom: 16px; }
        table.info { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table.info td { padding: 6px; vertical-align: top; }
        .amount-box { border: 2px solid #333; text-align: center; padding: 18px; font-size: 16px; font-weight: bold; margin: 20px 0; }
        .signatures { margin-top: 60px; }
        .signatures table { width: 100%; }
        .signatures td { width: 50%; vertical-align: top; text-align: center; }
    </style>
</head>
<body>
    @if($receipt->company->logo)
        <img src="{{ storage_path('app/public/' . $receipt->company->logo) }}" height="50" style="margin-bottom: 10px;" alt="logo">
    @endif

    <h1>CHITANȚĂ</h1>
    <p class="number">Nr. <strong>{{ $receipt->full_number }}</strong> | Data: <strong>{{ $receipt->issue_date->format('d.m.Y') }}</strong></p>

    <table class="info">
        <tr>
            <td><strong>Furnizor (primitor):</strong></td>
            <td>{{ $receipt->company->name }}<br>CIF: {{ $receipt->company->cif }}<br>{{ $receipt->company->address }}</td>
        </tr>
        <tr>
            <td><strong>Client (plătitor):</strong></td>
            <td>{{ $receipt->invoice->client->name }}<br>CIF/CNP: {{ $receipt->invoice->client->cif ?? $receipt->invoice->client->cnp ?? '—' }}</td>
        </tr>
        <tr>
            <td><strong>Factura aferentă:</strong></td>
            <td>Nr. {{ $receipt->invoice->full_number }} din {{ optional($receipt->invoice->issue_date)->format('d.m.Y') }}</td>
        </tr>
    </table>

    <div class="amount-box">
        Am primit suma de: {{ number_format((float) $receipt->amount, 2, ',', '.') }} {{ $receipt->currency }}
    </div>

    <p style="text-align: center;">Modalitate de plată: <strong>Numerar</strong></p>

    <div class="signatures">
        <table>
            <tr>
                <td><strong>Primit,</strong><br><br>{{ $receipt->received_by ?? $receipt->company->name }}<br><br>Semnătură: _______________</td>
                <td><strong>Plătit,</strong><br><br>{{ $receipt->invoice->client->name }}<br><br>Semnătură: _______________</td>
            </tr>
        </table>
    </div>
</body>
</html>
