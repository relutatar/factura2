<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        .header { margin-bottom: 20px; }
        .meta { margin-bottom: 12px; }
        .meta strong { display: inline-block; min-width: 160px; }
        .content { margin-top: 16px; line-height: 1.5; }
        .signatures { margin-top: 40px; width: 100%; }
        .signatures td { width: 50%; vertical-align: top; }
    </style>
</head>
<body>
    <div class="header">
        <h2 style="margin:0;">DECIZIE Nr. {{ $decision->number ?: '—' }} / {{ $decision->decision_date?->format('d.m.Y') ?? '—' }}</h2>
    </div>

    <div class="meta"><strong>Companie:</strong> {{ $decision->company?->name }}</div>
    <div class="meta"><strong>Număr:</strong> {{ $decision->number ?: '—' }}</div>
    <div class="meta"><strong>Data:</strong> {{ $decision->decision_date?->format('d.m.Y') ?? '—' }}</div>
    <div class="meta"><strong>Titlu:</strong> {{ $decision->title }}</div>
    <div class="meta"><strong>Reprezentant legal:</strong> {{ $decision->legal_representative_name }}</div>

    <div class="content">{!! $decision->content_snapshot !!}</div>

    @if($decision->notes)
        <div class="content">
            <strong>Observații:</strong> {{ $decision->notes }}
        </div>
    @endif

    <table class="signatures">
        <tr>
            <td>
                <strong>Reprezentant legal</strong><br><br>
                {{ $decision->legal_representative_name }}
            </td>
            <td style="text-align:right;">
                <strong>Semnătură</strong><br><br>
                ______________________
            </td>
        </tr>
    </table>
</body>
</html>
