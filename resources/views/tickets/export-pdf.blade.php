{{-- resources/views/tickets/export-pdf.blade.php --}}
@php
    use SimpleSoftwareIO\QrCode\Facades\QrCode;
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tickets — {{ $event->name }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        .grid { display: flex; flex-wrap: wrap; }
        .card { width: 48%; border: 1px solid #ddd; border-radius: 8px; padding: 10px; margin: 1%; }
        .title { font-weight: 700; font-size: 14px; }
        .meta { color: #666; font-size: 11px; margin-bottom: 8px; }
        .qr   { text-align: center; margin: 8px 0; }
    </style>
</head>
<body>
    <h2>Tickets — {{ $event->name }}</h2>
    <div class="grid">
        @foreach($registrations as $r)
            @php
                $payload = json_encode([
                    'type'=>'ticket',
                    'event'=>$event->public_id ?? $event->id,
                    'registration'=>$r->public_id ?? $r->id,
                ]);
                $png = base64_encode(QrCode::format('png')->size(220)->margin(1)->generate($payload));
            @endphp
            <div class="card">
                <div class="title">{{ $r->name ?? 'Guest' }}</div>
                <div class="meta">{{ $r->email }} · #{{ $r->public_id ?? $r->id }}</div>
                <div class="qr">
                    <img src="data:image/png;base64,{{ $png }}" alt="QR" />
                </div>
            </div>
        @endforeach
    </div>
</body>
</html>
