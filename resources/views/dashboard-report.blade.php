<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Eventib Organiser Report – {{ $year }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            font-size: 12px;
            color: #111827;
        }
        h1, h2, h3 {
            margin: 0 0 8px;
            color: #111827;
        }
        .muted {
            color: #6b7280;
            font-size: 11px;
        }
        .kpi-row {
            margin-top: 16px;
            margin-bottom: 16px;
            display: table;
            width: 100%;
        }
        .kpi {
            display: table-cell;
            width: 25%;
            padding: 8px 10px;
            border: 1px solid #e5e7eb;
        }
        .kpi-label {
            font-size: 11px;
            color: #6b7280;
            text-transform: uppercase;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            margin-top: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }
        th, td {
            padding: 6px 8px;
            border: 1px solid #e5e7eb;
            font-size: 11px;
            text-align: left;
        }
        th {
            background: #f3f4f6;
        }
        .text-right {
            text-align: right;
        }
        .mt-2 { margin-top: 8px; }
        .mt-4 { margin-top: 16px; }
    </style>
</head>
<body>
    <h1>Eventib Organiser Report</h1>
    <div class="muted">
        Organiser: {{ $organizer->name ?? $organizer->slug ?? 'N/A' }}<br>
        Year: {{ $year }}
    </div>

    <div class="kpi-row">
        <div class="kpi">
            <div class="kpi-label">Events posted ({{ $year }})</div>
            <div class="kpi-value">{{ number_format($eventsCount) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Total earnings ({{ $year }})</div>
            <div class="kpi-value">
                £{{ number_format($totalEarnings / 100, 2) }}
            </div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Registered attendees ({{ $year }})</div>
            <div class="kpi-value">{{ number_format($totalAttendees) }}</div>
        </div>
        <div class="kpi">
            <div class="kpi-label">Total check-ins ({{ $year }})</div>
            <div class="kpi-value">{{ number_format($totalCheckins) }}</div>
        </div>
    </div>

    <h2 class="mt-4">
        @switch($metric)
            @case('events') Monthly events @break
            @case('earnings') Monthly earnings @break
            @case('checkins') Monthly check-ins @break
            @default Monthly registered attendees
        @endswitch
        – {{ $year }}
    </h2>
    <div class="muted mt-2">
        Values shown per calendar month.
        @if($metric === 'earnings')
            Amounts in GBP.
        @endif
    </div>

    @php
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    @endphp

    <table>
        <thead>
            <tr>
                <th>Month</th>
                <th class="text-right">
                    @switch($metric)
                        @case('events') Events @break
                        @case('earnings') Earnings (£) @break
                        @case('checkins') Check-ins @break
                        @default Attendees
                    @endswitch
                </th>
            </tr>
        </thead>
        <tbody>
            @foreach($months as $i => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="text-right">
                        @php $val = $chartData[$i] ?? 0; @endphp
                        @if($metric === 'earnings')
                            £{{ number_format($val, 2) }}
                        @else
                            {{ number_format($val) }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="mt-4 muted">
        Generated on {{ now()->format('d M Y H:i') }} via Eventib.
    </div>
</body>
</html>
