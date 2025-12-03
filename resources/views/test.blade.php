{{-- resources/views/test.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Voice Pass — Test Console
                </h2>
                <p class="text-sm text-gray-500">
                    Record audio in the browser and send it to the voice service
                    (generate an embedding or compare against an existing one).
                </p>
            </div>
        </div>
    </x-slot>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Left: Recording panel --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5">
                <h3 class="text-lg font-semibold text-gray-800 mb-2">1. Record a sample</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Click <strong>Start recording</strong>, speak for a few seconds, then click <strong>Stop</strong>.
                    The sample will be sent to the voice service.
                </p>

                <div class="flex items-center gap-4 mb-4">
                    <button id="recordBtn"
                            type="button"
                            class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700 disabled:opacity-60">
                        🎙️ Start recording
                    </button>

                    <div id="recordStatus" class="text-sm text-gray-600">
                        Idle.
                    </div>
                </div>

                <div class="mb-4">
                    <label class="text-sm font-medium text-gray-700 mb-1 block">
                        Mode
                    </label>
                    <div class="flex flex-col gap-2 text-sm text-gray-700">
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="mode" value="compare" class="border-gray-300" checked>
                            <span>Compare against existing embedding</span>
                        </label>
                        <label class="inline-flex items-center gap-2">
                            <input type="radio" name="mode" value="embed" class="border-gray-300">
                            <span>Generate embedding only (for debugging / manual copy)</span>
                        </label>
                    </div>
                </div>

                <div id="thresholdBlock" class="mb-4">
                    <label class="text-sm font-medium text-gray-700 mb-1 block">
                        Match threshold (<span id="thresholdValue">0.80</span>)
                    </label>
                    <input id="thresholdInput"
                           type="range"
                           min="0.50" max="0.99" step="0.01"
                           value="0.80"
                           class="w-full">
                    <p class="text-xs text-gray-500 mt-1">
                        Higher = stricter match. For now we’re using 0.80 by default.
                    </p>
                </div>

                <div class="mt-4 text-xs text-gray-500">
                    Make sure your browser has microphone permission for <code>localhost</code>.
                </div>
            </div>

            {{-- Right: Embedding + result panel --}}
            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-5 flex flex-col gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 mb-2">
                        2. Embedding &amp; comparison
                    </h3>
                    <p class="text-sm text-gray-600 mb-2">
                        Paste an existing embedding from the database below (for compare mode), or leave it empty if
                        you just want to generate a new one.
                    </p>
                    <label for="embeddingInput" class="text-sm font-medium text-gray-700 mb-1 block">
                        Reference embedding JSON (from <code>event_registrations.voice_embedding</code>)
                    </label>
                    <textarea id="embeddingInput"
                              class="w-full min-h-[120px] rounded-lg border-gray-300 text-sm font-mono p-2"
                              placeholder='[0.123, -0.456, ...]'></textarea>
                    <p class="text-xs text-gray-500 mt-1">
                        For compare mode, this must be valid JSON (an array of numbers).
                    </p>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide">Last result</div>
                            <div id="resultMain" class="mt-1 text-lg font-semibold text-gray-700">
                                Waiting for a recording…
                            </div>
                            <div id="resultSub" class="mt-1 text-sm text-gray-600"></div>
                        </div>
                        <div id="resultBadge"
                             class="shrink-0 inline-flex px-2 py-1 rounded-full text-xs bg-gray-100 text-gray-700">
                            Idle
                        </div>
                    </div>

                    <details class="mt-3 text-xs text-gray-700">
                        <summary class="cursor-pointer text-gray-600 hover:text-gray-800">
                            Raw response
                        </summary>
                        <pre id="rawOutput"
                             class="mt-2 bg-gray-50 rounded-lg p-2 overflow-x-auto text-[11px] text-gray-700"></pre>
                    </details>
                </div>
            </div>

        </div>
    </div>

    <script>
        (function () {
            const recordBtn      = document.getElementById('recordBtn');
            const recordStatus   = document.getElementById('recordStatus');
            const modeRadios     = document.querySelectorAll('input[name="mode"]');
            const thresholdBlock = document.getElementById('thresholdBlock');
            const thresholdInput = document.getElementById('thresholdInput');
            const thresholdValue = document.getElementById('thresholdValue');
            const embeddingInput = document.getElementById('embeddingInput');

            const resultMain   = document.getElementById('resultMain');
            const resultSub    = document.getElementById('resultSub');
            const resultBadge  = document.getElementById('resultBadge');
            const rawOutput    = document.getElementById('rawOutput');

            let mediaStream = null;
            let mediaRecorder = null;
            let chunks = [];
            let isRecording = false;

            // --- Helpers ---------------------------------------------------

            function currentMode() {
                return Array.from(modeRadios).find(r => r.checked)?.value || 'compare';
            }

            function setBadge(text, tone = 'gray') {
                const map = {
                    green: 'bg-emerald-100 text-emerald-800',
                    red: 'bg-rose-100 text-rose-700',
                    amber: 'bg-amber-100 text-amber-800',
                    blue: 'bg-sky-100 text-sky-800',
                    gray: 'bg-gray-100 text-gray-700',
                };
                resultBadge.textContent = text;
                resultBadge.className = 'shrink-0 inline-flex px-2 py-1 rounded-full text-xs ' + (map[tone] || map.gray);
            }

            function showResult(main, sub = '', tone = 'gray') {
                resultMain.textContent = main;
                resultSub.textContent = sub;
                resultMain.className = 'mt-1 text-lg font-semibold ' + (
                    tone === 'green' ? 'text-emerald-700'
                        : tone === 'red' ? 'text-rose-700'
                            : tone === 'amber' ? 'text-amber-700'
                                : 'text-gray-700'
                );
            }

            function setStatus(text) {
                recordStatus.textContent = text;
            }

            thresholdInput.addEventListener('input', () => {
                thresholdValue.textContent = thresholdInput.value;
            });

            modeRadios.forEach(r => {
                r.addEventListener('change', () => {
                    const mode = currentMode();
                    thresholdBlock.style.display = mode === 'compare' ? 'block' : 'none';
                });
            });

            // --- Recording logic -------------------------------------------

            async function startRecording() {
                try {
                    if (!mediaStream) {
                        mediaStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    }

                    chunks = [];
                    mediaRecorder = new MediaRecorder(mediaStream, { mimeType: 'audio/webm' });

                    mediaRecorder.ondataavailable = e => {
                        if (e.data && e.data.size > 0) {
                            chunks.push(e.data);
                        }
                    };

                    mediaRecorder.onstop = async () => {
                        setStatus('Processing sample…');

                        const blob = new Blob(chunks, { type: 'audio/webm' });
                        chunks = [];

                        try {
                            await sendToVoiceService(blob);
                        } catch (err) {
                            console.error(err);
                            setBadge('Error', 'red');
                            showResult('❌ Error talking to voice service', err?.message || '', 'red');
                            rawOutput.textContent = (err && err.stack) ? err.stack : String(err);
                        } finally {
                            setStatus('Idle. You can record again.');
                        }
                    };

                    mediaRecorder.start();
                    isRecording = true;
                    recordBtn.textContent = '⏹ Stop recording';
                    recordBtn.classList.remove('bg-emerald-600', 'hover:bg-emerald-700');
                    recordBtn.classList.add('bg-rose-600', 'hover:bg-rose-700');
                    setStatus('Recording… speak now.');
                    setBadge('Recording', 'blue');

                } catch (err) {
                    console.error(err);
                    showResult('❌ Could not start recording', err?.message || '', 'red');
                    setBadge('Mic error', 'red');
                    setStatus('Microphone error — check browser permissions.');
                }
            }

            function stopRecording() {
                if (mediaRecorder && isRecording) {
                    mediaRecorder.stop();
                }
                isRecording = false;
                recordBtn.textContent = '🎙️ Start recording';
                recordBtn.classList.remove('bg-rose-600', 'hover:bg-rose-700');
                recordBtn.classList.add('bg-emerald-600', 'hover:bg-emerald-700');
            }

            recordBtn.addEventListener('click', () => {
                if (!isRecording) {
                    startRecording();
                } else {
                    stopRecording();
                }
            });

            // --- Call FastAPI service --------------------------------------

            async function sendToVoiceService(blob) {
                const mode = currentMode();
                const formData = new FormData();
                formData.append('audio', blob, 'sample.webm');

                let url = 'http://127.0.0.1:8001/embed';

                if (mode === 'compare') {
                    const refText = embeddingInput.value.trim();
                    if (!refText) {
                        showResult('❌ No reference embedding', 'Paste an embedding JSON in the box on the right.', 'red');
                        setBadge('Missing embedding', 'red');
                        return;
                    }

                    url = 'http://127.0.0.1:8001/compare';
                    formData.append('reference_embedding', refText);
                    formData.append('threshold', thresholdInput.value);
                }

                setBadge('Contacting service…', 'blue');
                showResult('Processing…', 'Talking to voice service at 127.0.0.1:8001', 'gray');

                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                });

                const text = await res.text();
                let json = null;
                try {
                    json = JSON.parse(text);
                } catch (_) {
                    // not JSON? show raw
                }

                rawOutput.textContent = JSON.stringify(json ?? text, null, 2);

                if (!res.ok) {
                    const msg = (json && json.detail) ? json.detail : `HTTP ${res.status}`;
                    showResult('❌ Service error', msg, 'red');
                    setBadge('Error', 'red');
                    return;
                }

                if (mode === 'embed') {
                    // json.embedding is the vector
                    const len = Array.isArray(json.embedding) ? json.embedding.length : 0;
                    showResult('✅ Embedding generated', `Length: ${len} – copy it into voice_embedding if needed.`, 'green');
                    setBadge('OK', 'green');
                    // Optionally auto-fill the box so you can reuse it
                    embeddingInput.value = JSON.stringify(json.embedding);
                    return;
                }

                // compare mode
                const sim  = json.similarity ?? 0;
                const thr  = json.threshold ?? thresholdInput.value;
                const match = !!json.match;

                const simPct = (sim * 100).toFixed(1);
                const thrPct = (thr * 100).toFixed(1);

                if (match) {
                    showResult(
                        '✅ Voice match',
                        `Similarity ${simPct}% (threshold ${thrPct}%)`,
                        'green'
                    );
                    setBadge('Match', 'green');
                } else {
                    showResult(
                        '❌ Voice does not match',
                        `Similarity ${simPct}% (threshold ${thrPct}%)`,
                        'red'
                    );
                    setBadge('No match', 'red');
                }
            }

            // Initial UI state
            setStatus('Idle. Click “Start recording” to begin.');
            thresholdBlock.style.display = 'block';
        })();
    </script>
</x-app-layout>
