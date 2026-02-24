<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <title>Contract {{ $contract->number }}</title>
    <style>
        @page { margin: 60px 100px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11.5px;
            color: #1f2937;
            line-height: 1.55;
            margin: 0;
        }
        .document {
            padding: 0;
        }
        .topbar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .topbar td {
            vertical-align: top;
        }
        .company-title {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #111827;
            margin-bottom: 4px;
        }
        .company-subtitle {
            font-size: 11px;
            color: #4b5563;
            line-height: 1.45;
        }
        .contract-badge {
            display: inline-block;
            border: 1px solid #d1d5db;
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #374151;
            margin-bottom: 6px;
        }
        .meta-block {
            text-align: right;
            font-size: 11px;
            color: #374151;
            line-height: 1.5;
        }
        .meta-label {
            font-weight: 700;
            color: #111827;
        }
        .divider {
            border-top: 1px solid #e5e7eb;
            margin: 10px 0 14px;
        }
        .template-content {
            margin-top: 4px;
        }
        .template-content .doc-kicker {
            margin: 0 0 2px;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            font-size: 9.5px;
            color: #6b7280;
        }
        .template-content .doc-title {
            margin: 0 0 2px;
            text-align: center;
            font-size: 18px;
            color: #111827;
        }
        .template-content .doc-subtitle {
            margin: 0 0 12px;
            text-align: center;
            font-size: 12px;
            font-weight: 700;
            color: #374151;
        }
        .template-content .doc-number {
            margin: 0 0 14px;
            text-align: center;
            font-size: 11px;
            color: #374151;
        }
        .template-content h1,
        .template-content h2,
        .template-content h3 {
            margin: 14px 0 7px;
            color: #111827;
        }
        .template-content h3 {
            border-left: 3px solid #9ca3af;
            padding-left: 8px;
            font-size: 12.5px;
        }
        .template-content p {
            margin: 6px 0;
            text-align: justify;
        }
        .template-content ul,
        .template-content ol {
            margin: 6px 0 8px 18px;
            padding: 0;
        }
        .template-content li {
            margin: 2px 0;
        }
        .template-content .party-table,
        .template-content .summary-table,
        .template-content table {
            width: 100%;
            border-collapse: collapse;
        }
        .template-content .party-table td,
        .template-content .summary-table td {
            border: 1px solid #e5e7eb;
            padding: 7px 8px;
            vertical-align: top;
        }
        .template-content .party-label,
        .template-content .summary-label {
            width: 150px;
            font-weight: 700;
            color: #111827;
            background: #f9fafb;
        }
        .template-content .section-note {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-left: 3px solid #9ca3af;
            padding: 7px 9px;
            margin-top: 8px;
            color: #374151;
        }
        .template-content .signature-table {
            width: 100%;
            margin-top: 28px;
            border-collapse: collapse;
        }
        .template-content .signature-table td {
            width: 50%;
            padding-top: 22px;
            vertical-align: top;
            font-size: 11px;
            border: none;
        }
        .template-content .signature-title {
            font-weight: 700;
            color: #111827;
        }
        .template-content .signature-line {
            margin-top: 42px;
            border-top: 1px solid #9ca3af;
            width: 86%;
        }
    </style>
</head>
<body>
    <div class="document">
        <table class="topbar">
            <tr>
                <td>
                    <div class="company-title">{{ $contract->company->name }}</div>
                    <div class="company-subtitle">
                        CIF {{ $contract->company->cif }} | Reg. Com. {{ $contract->company->reg_com }}<br>
                        {{ $contract->company->address }}, {{ $contract->company->city }}, {{ $contract->company->county }}
                    </div>
                </td>
                <td style="width: 42%;">
                    <div class="meta-block">
                        <div class="contract-badge">{{ $contract->template?->name ?? 'Contract' }}</div><br>
                        <span class="meta-label">Nr. contract:</span> {{ $contract->number }}<br>
                        <span class="meta-label">Din data:</span> {{ $contract->signed_date?->format('d.m.Y') ?? $contract->start_date?->format('d.m.Y') }}<br>
                        <span class="meta-label">Client:</span> {{ $contract->client->name }}<br>
                        <span class="meta-label">Data început:</span> {{ $contract->start_date?->format('d.m.Y') }}<br>
                        <span class="meta-label">Data sfârșit:</span> {{ $contract->end_date?->format('d.m.Y') ?? 'nedeterminat' }}
                    </div>
                </td>
            </tr>
        </table>

        <div class="divider"></div>

        <div class="template-content">
            {!! $content !!}
        </div>
    </div>
</body>
</html>
