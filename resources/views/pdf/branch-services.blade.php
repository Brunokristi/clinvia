<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">

    <title>Služby</title>

    <style>
        @page {
            margin: 36px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.5;
        }

        .header {
            text-align: center;
            margin-bottom: 32px;
        }

        .title {
            font-size: 26px;
            font-weight: bold;
            margin: 0 0 8px;
            color: #111827;
        }

        .branch-name {
            font-size: 15px;
            margin: 0;
            color: #4b5563;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th {
            background: #f3f4f6;
            color: #111827;
            text-align: left;
            font-weight: bold;
            padding: 12px;
            border-bottom: 1px solid #d1d5db;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            vertical-align: top;
        }

        .service-name {
            width: 46%;
            font-weight: bold;
        }

        .price {
            width: 27%;
        }

        .empty {
            text-align: center;
            color: #6b7280;
            padding: 24px;
        }

        .footer {
            position: fixed;
            bottom: -12px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
        }
    </style>
</head>

<body>
    <div class="header">
        <h1 class="title">Služby</h1>

        <p class="branch-name">
            {{ $branch->name }}
        </p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Služba</th>
                <th>Cena samoplatca</th>
                <th>Cena cez poisťovňu</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($services as $service)
                <tr>
                    <td class="service-name">
                        {{ $service->name }}
                    </td>

                    <td class="price">
                        @if ($service->self_pay_amount !== null)
                            {{ number_format((float) $service->self_pay_amount, 2, ',', ' ') }} €
                        @else
                            —
                        @endif
                    </td>

                    <td class="price">
                        @if ($service->insurance_amount !== null)
                            {{ number_format((float) $service->insurance_amount, 2, ',', ' ') }} €
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td
                        colspan="3"
                        class="empty"
                    >
                        Táto pobočka zatiaľ nemá žiadne služby.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>