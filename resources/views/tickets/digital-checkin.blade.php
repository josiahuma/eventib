<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Digital Pass check-in — {{ $event->name }}
                </h2>
                <p class="text-sm text-gray-500">
                    Ask the attendee to say their pass phrase. We’ll match their voice and check them in.
                </p>
            </div>
            <div class="flex items-center gap-4 text-sm">
                <a href="{{ route('tickets.scan', $event) }}" class="text-gray-600 hover:text-gray-800 underline">
                    QR scanner
                </a>
                <a href="{{ route('events.checkins.index', $event) }}" class="text-gray-600 hover:text-gray-800 underline">
                    View check-ins
                </a>
                <a href="{{ route('events.registrants', $event) }}" class="text-gray-600 hover:text-gray-800 underline">
                    Back to registrants
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-6">
            @if($mode === 'off')
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Digital Pass is <strong>disabled</strong> for this event. Only QR codes can be used to check in.
                </div>
            @endif

            <div>
                <h3 class="text-lg font-semibold text-gray-900">Record attendee</h3>
                <p class="mt-1 text-sm text-gray-600">
                    Hold the device’s microphone near the attendee and tap <strong>Start recording</strong>.
                    Ask them to clearly say the phrase they used when setting up their Digital Pass.
                </p>
            </div>

            <div x-data="voiceCheckin()" class="space-y-4">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium text-white"
                        :class="isRecording ? 'bg-rose-600 hover:bg-rose-700' : 'bg-indigo-600 hover:bg-indigo-700'"
                        @click="toggleRecording"
                        x-text="isRecording ? 'Stop recording' : 'Start recording'">
                    </button>

                    <div class="text-sm text-gray-600" x-text="statusText"></div>
                </div>

                <div x-show="hasPreview" class="space-y-2">
                    <div class="text-sm text-gray-700 font-medium">Preview</div>
                    <audio controls :src="previewUrl" class="w-full"></audio>
                </div>

                <template x-if="error">
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="error"></div>
                </template>

                <template x-if="result">
                    <div class="rounded-lg border px-4 py-3 text-sm"
                         :class="result.ok ? 'border-emerald-200 bg-emerald-50 text-emerald-900' : 'border-amber-200 bg-amber-50 text-amber-900'">
                        <template x-if="result.ok">
                            <div>
                                <div class="font-semibold">
                                    <span x-text="result.already ? '⚠️ Already checked-in' : '✅ Checked-in via Digital Pass'"></span>
                                </div>
                                <div class="mt-1">
                                    <span x-text="result.name"></span>
                                    <span class="text-gray-600">(&nbsp;</span>
                                    <span class="text-gray-600" x-text="result.email"></span>
                                    <span class="text-gray-600">&nbsp;)</span>
                                </div>
                                <div class="mt-1 text-xs text-gray-700">
                                    Party size: <span x-text="result.party"></span>
                                    · Sessions: <span x-text="result.sessions"></span>
                                    · Confidence: <span x-text="result.score"></span>
                                </div>
                            </div>
                        </template>
                        <template x-if="!result.ok">
                            <div class="rounded-lg border px-4 py-3 text-sm border-amber-200 bg-amber-50 text-amber-900">
                                <div class="font-semibold">
                                    No confident match
                                </div>

                                <div class="mt-1">
                                    @if (! $hasDigitalPassAttendees)
                                        {{-- Event genuinely has no digital-pass registrations --}}
                                        No digital-pass attendees found for this event.
                                    @else
                                        {{-- There ARE digital-pass attendees, this is just a low-confidence match --}}
                                        <span x-text="result.error"></span>
                                    @endif
                                </div>

                                <div class="mt-1 text-xs text-gray-700" x-show="result.best">
                                    Closest attendee: <span x-text="result.name"></span>
                                    (&nbsp;<span x-text="result.email"></span>&nbsp;),
                                    confidence <span x-text="result.best"></span>.
                                </div>
                            </div>
                        </template>

                    </div>
                </template>
            </div>
        </div>
    </div>

    <script>
        function voiceCheckin() {
            return {
                isRecording: false,
                mediaRecorder: null,
                chunks: [],
                previewUrl: '',
                hasPreview: false,
                statusText: 'Ready to record.',
                error: '',
                result: null,

                async toggleRecording() {
                    this.error = '';
                    this.result = null;

                    if (this.isRecording) {
                        this.stopRecording();
                        return;
                    }

                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.error = 'This browser does not support audio recording.';
                        return;
                    }

                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.chunks = [];
                        this.mediaRecorder = new MediaRecorder(stream);

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data && e.data.size > 0) this.chunks.push(e.data);
                        };

                        this.mediaRecorder.onstop = () => {
                            const blob = new Blob(this.chunks, { type: 'audio/webm' });
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = URL.createObjectURL(blob);
                            this.hasPreview = true;
                            this.statusText = 'Recorded. Sending for match…';
                            this.sendToServer(blob);

                            // stop tracks
                            this.mediaRecorder.stream.getTracks().forEach(t => t.stop());
                        };

                        this.mediaRecorder.start();
                        this.isRecording = true;
                        this.statusText = 'Recording… tap again to stop.';
                    } catch (e) {
                        console.error(e);
                        this.error = 'Could not access microphone. Check permissions.';
                    }
                },

                stopRecording() {
                    if (this.mediaRecorder && this.isRecording) {
                        this.mediaRecorder.stop();
                        this.isRecording = false;
                        this.statusText = 'Processing recording…';
                    }
                },

                async sendToServer(blob) {
                    try {
                        const b64 = await this.blobToDataUrl(blob);
                        const res = await fetch("{{ route('tickets.digital.checkin.voice', $event) }}", {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ audio: b64 }),
                        });

                        const data = await res.json();
                        this.result = data;
                        if (!data.ok) {
                            this.statusText = 'No match found.';
                        } else {
                            this.statusText = data.already
                                ? 'Attendee was already checked-in.'
                                : 'Attendee checked-in via Digital Pass.';
                        }
                    } catch (e) {
                        console.error(e);
                        this.error = 'Network error. Please try again.';
                        this.statusText = 'Network error.';
                    }
                },

                blobToDataUrl(blob) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onloadend = () => resolve(reader.result);
                        reader.onerror = reject;
                        reader.readAsDataURL(blob);
                    });
                }
            };
        }
    </script>
</x-app-layout>
