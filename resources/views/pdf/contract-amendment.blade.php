<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; margin: 40px; }
        h1 { font-size: 14px; text-align: center; text-transform: uppercase; }
        .subtitle { text-align: center; font-size: 11px; color: #555; margin-bottom: 20px; }
        .signatures { margin-top: 60px; }
        .signatures table { width: 100%; }
        .signatures td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
    <h1>ACT ADIȚIONAL NR. {{ $amendment->amendment_number }}</h1>
    <p class="subtitle">
        la Contractul nr. {{ $amendment->contract->number }}
        din {{ $amendment->contract->signed_date?->format('d.m.Y') ?? $amendment->contract->start_date?->format('d.m.Y') }}
    </p>
    <p>Datat: {{ $amendment->signed_date?->format('d.m.Y') ?? '____.____.________' }}</p>

    <div>{!! $amendment->content_snapshot ?? $amendment->body !!}</div>

    <div class="signatures">
        <table>
            <tr>
                <td><strong>Prestator:</strong><br>{{ $amendment->contract->company->name }}<br><br>Semnătură: _______________</td>
                <td><strong>Beneficiar:</strong><br>{{ $amendment->contract->client->name }}<br><br>Semnătură: _______________</td>
            </tr>
        </table>
    </div>
</body>
</html>
