{{-- resources/views/tickets/pdf/registration.blade.php --}}
@php($font = "font-family: DejaVu Sans, sans-serif;")
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Tickets — {{ $event->name }}</title>
    <style>
        * { {{ $font }} }
        .card { border:1px solid #ddd; border-radius:10px; padding:12px; margin-bottom:12px; }
        .grid { display:grid; grid-template-columns: 1fr 220px; gap:12px; align-items:center; }
        .small { color:#555; font-size:12px; }
        img.qrimg { width:200px; height:200px; }
    </style>
</head>
<body>
<h2>Tickets — {{ $event->name }}</h2>
@if($event->location)<div class="small">{{ $event->location }}</div>@endif
<hr>
@foreach($tickets as $t)
    <div class="card">
        <div class="grid">
            <div>
                <div><strong>Ticket #{{ $t->serial }}</strong></div>
                <div class="small">Show this QR at the door.</div>
                @if($t->checked_in_at)
                    <div class="small">
                        Checked in: {{ \Illuminate\Support\Carbon::parse($t->checked_in_at)->format('d M Y, g:ia') }}
                    </div>
                @endif
            </div>
            <div class="qr">
                @if(!empty($qrPaths[$t->id]))
                    <img class="qrimg" src="{{ $qrPaths[$t->id] }}" alt="QR code">
                @elseif(!empty($qrSvgDataUri[$t->id]))
                    <img class="qrimg" src="{{ $qrSvgDataUri[$t->id] }}" alt="QR code">
                @else
                    <div class="small">QR unavailable</div>
                @endif
            </div>
        </div>
    </div>
@endforeach
</body>
</html>
