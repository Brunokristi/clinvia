<!DOCTYPE html>
<html lang="sk">
<head>
    <meta charset="UTF-8">

    <title>Otváracie hodiny</title>

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

        .day {
            font-weight: bold;
            width: 26%;
        }

        .hours {
            width: 34%;
        }

        .note {
            width: 40%;
            color: #4b5563;
        }

        .closed {
            color: #991b1b;
            font-weight: bold;
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
        <h1 class="title">Otváracie hodiny</h1>

        <p class="branch-name">
            {{ $branch->name }}
        </p>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>Deň</th>
                <th>Hodiny</th>
                <th>Poznámka</th>
            </tr>
        </thead>

        <tbody>
            @foreach ($openingHours as $day)
                <tr>
                    <td class="day">
                        {{ match ((int) $day->day_of_week) {
                            1 => 'Pondelok',
                            2 => 'Utorok',
                            3 => 'Streda',
                            4 => 'Štvrtok',
                            5 => 'Piatok',
                            6 => 'Sobota',
                            7 => 'Nedeľa',
                            default => 'Neznámy deň',
                        } }}
                    </td>

                    <td class="hours">
                        @if ($day->is_closed)
                            <span class="closed">Zatvorené</span>
                        @elseif ($day->intervals->isEmpty())
                            Bez zadaných hodín
                        @else
                            @foreach ($day->intervals->sortBy('sort_order') as $interval)
                                <div>
                                    {{ substr($interval->opens_at, 0, 5) }}
                                    –
                                    {{ substr($interval->closes_at, 0, 5) }}
                                </div>
                            @endforeach
                        @endif
                    </td>

                    <td class="note">
                        {{ $day->note ?: '—' }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>