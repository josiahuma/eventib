{{-- resources/views/tickets/scan.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Scan tickets — {{ $event->name }}
                </h2>
                <p class="text-sm text-gray-500">
                    Aim at a QR. We’ll auto-check it in if valid.
                </p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('events.checkins.index', $event) }}" class="text-gray-600 hover:text-gray-800 underline">
                    View check-ins
                </a>
                <a href="{{ route('events.registrants', $event) }}" class="text-gray-600 hover:text-gray-800 underline">
                    Back to registrants
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Scanner panel --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4">
                <div class="flex flex-wrap items-center gap-2 justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <label for="cameraSelect" class="text-sm text-gray-600">Camera</label>
                        <select id="cameraSelect" class="rounded-lg border-gray-300 text-sm min-w-[10rem]">
                            <option>Loading…</option>
                        </select>
                        <button id="flipBtn" type="button"
                                class="px-3 py-1.5 rounded-md bg-gray-100 hover:bg-gray-200 text-sm"
                                title="Switch camera">
                            Flip
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-600">
                            <input id="beepToggle" type="checkbox" class="rounded border-gray-300" checked>
                            Beep
                        </label>
                        <button id="pauseBtn" type="button"
                                class="px-3 py-1.5 rounded-md bg-amber-100 text-amber-800 hover:bg-amber-200 text-sm"
                                title="Pause scanning">
                            Pause
                        </button>
                    </div>
                </div>

                <div class="relative rounded-xl overflow-hidden bg-black">
                    {{-- html5-qrcode will inject a <video> here --}}
                    <div id="reader" class="w-full aspect-[3/4]"></div>

                    {{-- Subtle targeting frame (purely visual) --}}
                    <div class="pointer-events-none absolute inset-0 grid place-items-center">
                        <div class="w-48 h-48 border-2 border-white/70 rounded-md"></div>
                    </div>
                </div>

                <p class="mt-3 text-xs text-gray-500">
                    Tip: if it opens the front camera, use <b>Flip</b> or choose the back camera from the list.
                </p>
            </div>

            {{-- Result panel --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-500">Last scan</div>
                        <div id="result" class="mt-1 text-lg font-semibold text-gray-700">Waiting…</div>
                        <div id="sub" class="mt-1 text-sm text-gray-600"></div>
                    </div>
                    <div id="badge" class="shrink-0 inline-flex px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                        Idle
                    </div>
                </div>

                <hr class="my-4">

                <div>
                    <div class="text-sm text-gray-500 mb-2">Recent scans</div>
                    <ul id="history" class="space-y-2 text-sm">
                        <li class="text-gray-400">No scans yet.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- html5-qrcode --}}
    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
    (function () {
        const readerId   = "reader";
        const resultEl   = document.getElementById('result');
        const subEl      = document.getElementById('sub');
        const badgeEl    = document.getElementById('badge');
        const historyEl  = document.getElementById('history');
        const selectEl   = document.getElementById('cameraSelect');
        const flipBtn    = document.getElementById('flipBtn');
        const pauseBtn   = document.getElementById('pauseBtn');
        const beepToggle = document.getElementById('beepToggle');

        // Beep audio (tiny base64 WAV)
        const beepOk   = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABYA…'); // shortened for brevity
        const beepBad  = new Audio('data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABYA…'); // you can use the same or another

        const html5QrCode = new Html5Qrcode(readerId);
        let cameras = [];
        let currentIndex = -1;
        let running = false;
        let lastText = '';
        let cooldown = false;

        function setBadge(text, color) {
            const map = {
                green:  'bg-emerald-100 text-emerald-800',
                amber:  'bg-amber-100 text-amber-800',
                red:    'bg-rose-100 text-rose-700',
                gray:   'bg-gray-100 text-gray-700',
                blue:   'bg-sky-100 text-sky-800',
            };
            badgeEl.textContent = text;
            badgeEl.className = 'shrink-0 inline-flex px-2 py-1 rounded-full text-xs ' + (map[color] || map.gray);
        }

        function show(msg, sub, okState) {
            resultEl.textContent = msg;
            resultEl.className = 'mt-1 text-lg font-semibold ' + (okState === true
                ? 'text-emerald-700'
                : okState === false
                    ? 'text-rose-700'
                    : 'text-gray-700');
            subEl.textContent = sub || '';
        }

        function pushHistory(line, isOk) {
            // keep last 6 items
            const li = document.createElement('li');
            li.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="${isOk ? 'text-emerald-700' : 'text-rose-700'} font-medium">${line.title}</span>
                    <span class="text-gray-500">${new Date().toLocaleTimeString()}</span>
                </div>
                <div class="text-gray-600">${line.sub || ''}</div>
            `;
            if (historyEl.firstElementChild && historyEl.firstElementChild.classList.contains('text-gray-400')) {
                historyEl.innerHTML = '';
            }
            historyEl.prepend(li);
            while (historyEl.children.length > 6) historyEl.removeChild(historyEl.lastChild);
        }

        async function sendToServer(text) {
            try {
                const res = await fetch("{{ route('tickets.scan.validate', $event) }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ payload: text })
                });
                const data = await res.json();

                if (!data.ok) {
                    if (beepToggle.checked) beepBad.play().catch(()=>{});
                    navigator.vibrate?.(120);
                    setBadge('Invalid', 'red');
                    show('❌ Invalid', data.reason || '', false);
                    pushHistory({ title: '❌ Invalid', sub: data.reason || '' }, false);
                    return;
                }

                if (beepToggle.checked) beepOk.play().catch(()=>{});
                navigator.vibrate?.(40);

                // Build nice subtext based on your payload shape
                let sub = '';
                if (data.type === 'paid') {
                    sub = `Ticket #${data.serial}` + (data.already ? ' · already checked-in' : '');
                } else if (data.type === 'free') {
                    sub = `Party size ${data.party}` + (data.already ? ' · already checked-in' : '');
                }

                setBadge(data.already ? 'Already checked' : 'Checked-in', data.already ? 'amber' : 'green');
                show(data.already ? '⚠️ Already checked-in' : '✅ Checked-in', sub, !data.already);
                pushHistory({ title: data.already ? '⚠️ Already' : '✅ Checked', sub }, !data.already);

            } catch (e) {
                console.error(e);
                if (beepToggle.checked) beepBad.play().catch(()=>{});
                navigator.vibrate?.(120);
                setBadge('Network error', 'red');
                show('❌ Network error', '', false);
                pushHistory({ title: '❌ Network error', sub: '' }, false);
            }
        }

        function guessBackIndex(list) {
            const lower = s => (s || '').toLowerCase();
            const ix = list.findIndex(d => /back|rear|environment/i.test(lower(d.label)));
            if (ix >= 0) return ix;
            // Often the last one is the rear camera on phones
            return list.length > 1 ? list.length - 1 : 0;
        }

        async function populateCameraSelect() {
            try {
                const cams = await Html5Qrcode.getCameras();
                cameras = cams || [];
                selectEl.innerHTML = '';

                if (!cameras.length) {
                    const opt = document.createElement('option');
                    opt.textContent = 'No cameras found';
                    selectEl.appendChild(opt);
                    selectEl.disabled = true;
                    flipBtn.disabled = true;
                    return;
                }

                if (currentIndex === -1) {
                    // Use previously chosen camera if present
                    const savedId = localStorage.getItem('scanner.camId');
                    currentIndex = savedId
                        ? Math.max(0, cameras.findIndex(c => c.id === savedId))
                        : guessBackIndex(cameras);
                }

                cameras.forEach((cam, i) => {
                    const opt = document.createElement('option');
                    opt.value = cam.id;
                    opt.textContent = cam.label || `Camera ${i + 1}`;
                    selectEl.appendChild(opt);
                });
                selectEl.value = cameras[currentIndex].id;
                selectEl.disabled = cameras.length <= 1;
                flipBtn.disabled  = cameras.length <= 1;
            } catch (e) {
                console.warn('getCameras failed', e);
                selectEl.innerHTML = '<option>Camera error</option>';
            }
        }

        async function start(camId) {
            if (running) await stop();
            setBadge('Starting…', 'blue');

            const config = {
                fps: 12,
                qrbox: (viewfinderWidth, viewfinderHeight) => {
                    const s = Math.round(Math.min(viewfinderWidth, viewfinderHeight) * 0.5);
                    return { width: s, height: s };
                },
                // Hint rear camera if camId missing (desktop fallback):
                experimentalFeatures: {
                    useBarCodeDetectorIfSupported: true
                },
                rememberLastUsedCamera: false
            };

            try {
                await html5QrCode.start(
                    camId || { facingMode: 'environment' },
                    config,
                    async (decodedText /*, decodedResult*/) => {
                        if (cooldown) return;
                        if (decodedText === lastText) return;
                        lastText = decodedText;

                        // brief pause so we don’t double-fire while the phone is steady
                        cooldown = true;
                        await html5QrCode.pause(true);
                        await sendToServer(decodedText);
                        setTimeout(async () => {
                            try { await html5QrCode.resume(); } catch(e){}
                            cooldown = false;
                        }, 900);
                    },
                    /* error callback */ () => {}
                );
                running = true;
                setBadge('Scanning', 'blue');
                localStorage.setItem('scanner.camId', camId || '');
            } catch (e) {
                console.error('start failed', e);
                setBadge('Camera error', 'red');
                show('❌ Camera error', (e && e.message) || '', false);
            }
        }

        async function stop() {
            try {
                await html5QrCode.stop();
            } catch (_) {}
            running = false;
            setBadge('Idle', 'gray');
        }

        // UI events
        selectEl.addEventListener('change', async (e) => {
            const id = e.target.value;
            const ix = cameras.findIndex(c => c.id === id);
            if (ix !== -1) currentIndex = ix;
            await start(id);
        });

        flipBtn.addEventListener('click', async () => {
            if (!cameras.length) return;
            currentIndex = (currentIndex + 1) % cameras.length;
            selectEl.value = cameras[currentIndex].id;
            await start(cameras[currentIndex].id);
        });

        pauseBtn.addEventListener('click', async () => {
            if (!running) return;
            const paused = pauseBtn.dataset.state === 'paused';
            if (paused) {
                // resume
                try { await html5QrCode.resume(); } catch(e){}
                pauseBtn.textContent = 'Pause';
                pauseBtn.className = 'px-3 py-1.5 rounded-md bg-amber-100 text-amber-800 hover:bg-amber-200 text-sm';
                pauseBtn.dataset.state = 'running';
                setBadge('Scanning', 'blue');
            } else {
                // pause
                try { await html5QrCode.pause(true); } catch(e){}
                pauseBtn.textContent = 'Resume';
                pauseBtn.className = 'px-3 py-1.5 rounded-md bg-gray-100 text-gray-800 hover:bg-gray-200 text-sm';
                pauseBtn.dataset.state = 'paused';
                setBadge('Paused', 'gray');
            }
        });

        // Boot
        (async function init() {
            show('Waiting for camera…', '', null);
            await populateCameraSelect();

            if (cameras.length) {
                await start(cameras[currentIndex].id);
            } else {
                // Fallback try facingMode (some browsers allow without enumerate)
                await start(null);
            }
        })();
    })();
    </script>
</x-app-layout>
