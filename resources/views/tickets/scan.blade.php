{{-- resources/views/tickets/scan.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Scan tickets — {{ $event->name }}
            </h2>
            <a href="{{ route('events.checkins.index', $event) }}" class="text-sm text-gray-600 hover:text-gray-800 underline">View check-ins</a>
            <a href="{{ route('events.registrants', $event) }}" class="text-sm text-gray-600 hover:text-gray-800 underline">Back</a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid md:grid-cols-2 gap-6">
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
                <div id="reader" style="width:100%;"></div>
            </div>
            <div class="bg-white border border-gray-200 rounded-2xl p-4 shadow-sm">
                <div class="text-sm text-gray-500">Result</div>
                <div id="result" class="mt-2 text-lg font-semibold"></div>
                <div id="sub" class="mt-1 text-sm text-gray-600"></div>
            </div>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const resultEl = document.getElementById('result');
        const subEl = document.getElementById('sub');
        function show(msg, sub, ok=true) {
            resultEl.textContent = msg;
            resultEl.className = 'mt-2 text-lg font-semibold ' + (ok ? 'text-emerald-700' : 'text-rose-700');
            subEl.textContent = sub || '';
        }

        async function sendToServer(text) {
            try {
                const res = await fetch("{{ route('tickets.scan.validate', $event) }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                    body: JSON.stringify({ payload: text })
                });
                const data = await res.json();
                if (!data.ok) { show('❌ ' + (data.reason || 'Invalid ticket'), '', false); return; }
                if (data.already) show('⚠️ Already checked in', 'Ticket #' + data.serial, false);
                else              show('✅ Checked in',       'Ticket #' + data.serial, true);
            } catch (e) { console.error(e); show('❌ Network error', '', false); }
        }

        const html5QrCode = new Html5Qrcode("reader");
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };

        Html5Qrcode.getCameras().then(cams => {
            const camId = cams[0]?.id;
            if (!camId) { show('No camera found', '', false); return; }

            html5QrCode.start(
                camId,
                config,
                async (decodedText) => {
                    await html5QrCode.stop();          // throttle duplicates
                    await sendToServer(decodedText);
                    setTimeout(() => html5QrCode.start(camId, config, ()=>{}, ()=>{}), 600);
                },
                () => {}
            );
        }).catch(() => show('Camera error', '', false));
    </script>
</x-app-layout>
{{-- Note: we use the html5-qrcode library here because it's a simple, modern,   
     well-maintained library that works well in browsers without any build step. --}}