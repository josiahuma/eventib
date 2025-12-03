<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Digital Pass
        </h2>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8"
         x-data="voiceEnroll()">

        {{-- Status / flash messages --}}
        @if (session('success'))
            <div class="mb-6 p-4 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 p-4 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-sm">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-rose-50 text-rose-700 border border-rose-200 text-sm">
                <p class="font-semibold mb-1">We couldn’t save your voice pass:</p>
                <ul class="list-disc ml-4 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Status card --}}
        <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 mb-6 flex items-start justify-between gap-4">
            <div>
                <div class="text-xs font-semibold text-gray-500 tracking-wide">STATUS</div>
                @if($pass && $pass->is_active)
                    <div class="mt-1 flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 inline-block"></span>
                        <span class="text-sm font-medium text-gray-900">Voice pass active</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Enrolled {{ optional($pass->voice_enrolled_at)->format('d M Y, g:ia') ?? 'recently' }}
                    </p>
                @else
                    <div class="mt-1 text-sm font-medium text-gray-900">No digital pass yet</div>
                    <p class="mt-1 text-xs text-gray-500">
                        Set up your voice pass once and we’ll use it for check-in on any event where you opt in.
                    </p>
                @endif
            </div>

            @if($pass)
                <form method="POST" action="{{ route('digital-pass.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium bg-rose-50 text-rose-700 hover:bg-rose-100">
                        Delete pass
                    </button>
                </form>
            @endif
        </div>

        {{-- Main voice setup card --}}
        <form method="POST" action="{{ route('digital-pass.store.voice') }}" @submit="onSubmit">
            @csrf

            <div class="bg-white border border-gray-200 rounded-2xl shadow-sm p-6 space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Set up your voice pass</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            You’ll record the same short phrase <span class="font-semibold">three times</span>.
                            We use these samples to build a secure voice fingerprint that stays on Eventib and is
                            never shared with organisers.
                        </p>

                        <ol class="mt-3 text-xs text-gray-600 space-y-1">
                            <li>1. Tap <span class="font-semibold">Start recording</span> and say your phrase.</li>
                            <li>2. Tap <span class="font-semibold">Stop</span> to save the sample.</li>
                            <li>3. Repeat until all three samples show a green tick.</li>
                        </ol>
                    </div>

                    {{-- Big step badge --}}
                    <div class="shrink-0">
                        <div class="inline-flex items-center gap-1 px-3 py-1 rounded-full bg-slate-100 text-xs font-medium text-slate-700">
                            Step <span x-text="step"></span> of 3
                        </div>
                    </div>
                </div>

                {{-- Single sample card with step indicator --}}
                <div class="border rounded-xl p-5 bg-slate-50/60 flex flex-col gap-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="text-sm font-medium text-gray-900">
                                Current sample: <span x-text="step"></span> of 3
                            </div>
                            <p class="mt-1 text-xs text-gray-600">
                                Say the same phrase each time, e.g.
                                <span class="italic">“This is my Eventib voice pass.”</span>
                            </p>
                        </div>

                        {{-- three-step progress pills --}}
                        <div class="flex items-center gap-2 text-xs">
                            <template x-for="n in 3" :key="'dot-' + n">
                                <div class="flex items-center gap-1">
                                    <span class="inline-flex h-2 w-2 rounded-full"
                                          :class="completed.includes(n)
                                            ? 'bg-emerald-500'
                                            : (step === n ? 'bg-indigo-500' : 'bg-slate-300')">
                                    </span>
                                    <span class="text-[11px]"
                                          :class="completed.includes(n) ? 'text-emerald-700' : (step === n ? 'text-indigo-700' : 'text-slate-500')">
                                        Sample <span x-text="n"></span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- Record button + preview --}}
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex flex-col gap-2">
                            <div class="flex items-center gap-3">
                                <button type="button"
                                        class="inline-flex justify-center items-center px-4 py-2.5 rounded-lg text-sm font-medium shadow-sm"
                                        :class="recording
                                            ? 'bg-rose-600 text-white hover:bg-rose-700'
                                            : 'bg-indigo-600 text-white hover:bg-indigo-700 disabled:bg-slate-200 disabled:text-slate-500'"
                                        @click.prevent="toggleRecord"
                                        :disabled="saving">
                                    <span x-show="!recording">🎙️ Start recording</span>
                                    <span x-show="recording">⏹ Stop</span>
                                </button>

                                <div class="text-xs text-gray-500">
                                    Tip: find a quiet place and speak at your normal volume.
                                </div>
                            </div>

                            <p class="text-xs" :class="statusColour" x-text="statusText"></p>
                        </div>

                        <audio x-show="previewUrl" controls class="w-full sm:w-auto"
                               :src="previewUrl"></audio>
                    </div>

                    {{-- sample checklist rows --}}
                    <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                        <template x-for="n in 3" :key="'row-' + n">
                            <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg"
                                 :class="completed.includes(n)
                                    ? 'bg-emerald-50 border border-emerald-200'
                                    : 'bg-slate-50 border border-dashed border-slate-200'">
                                <span class="inline-flex h-4 w-4 rounded-full items-center justify-center text-[10px]"
                                      :class="completed.includes(n)
                                        ? 'bg-emerald-500 text-white'
                                        : (step === n ? 'bg-indigo-500 text-white' : 'bg-slate-200 text-slate-600')">
                                    <template x-if="completed.includes(n)">✓</template>
                                    <template x-if="!completed.includes(n)" x-text="n"></template>
                                </span>
                                <div>
                                    <div class="font-medium text-[11px] text-gray-800">
                                        Sample <span x-text="n"></span>
                                    </div>
                                    <div class="text-[11px] text-gray-500">
                                        <span x-show="completed.includes(n)">Recorded</span>
                                        <span x-show="!completed.includes(n) && step === n">Waiting to record…</span>
                                        <span x-show="!completed.includes(n) && step !== n && step > n">Will be overwritten later</span>
                                        <span x-show="!completed.includes(n) && step < n">Not yet recorded</span>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    <template x-if="error">
                        <p class="text-xs text-rose-600" x-text="error"></p>
                    </template>
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ url()->previous() }}" class="text-sm text-gray-600 hover:text-gray-800">
                        ← Back
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="!allDone || saving">
                        <span x-show="!saving">Save voice pass</span>
                        <span x-show="saving">Saving…</span>
                    </button>
                </div>

                <div class="border-t pt-4 mt-4">
                    <h4 class="text-sm font-semibold text-gray-800">Face ID (coming soon)</h4>
                    <p class="mt-1 text-xs text-gray-500">
                        We’re adding optional face recognition to your Digital Pass. For now, only voice
                        is used for check-in.
                    </p>
                </div>
            </div>

            {{-- Hidden inputs actually sent to Laravel --}}
            <input type="hidden" name="voice_sample1" x-ref="sample1">
            <input type="hidden" name="voice_sample2" x-ref="sample2">
            <input type="hidden" name="voice_sample3" x-ref="sample3">
        </form>
    </div>

    <script>
        function voiceEnroll() {
            return {
                step: 1,
                completed: [],
                recording: false,
                mediaRecorder: null,
                currentStream: null,
                chunks: [],
                previewUrl: null,
                error: '',
                saving: false,

                statusText: 'Ready to record sample 1 of 3. Tap “Start recording”.',
                statusColour: 'text-xs text-slate-600',

                get allDone() {
                    return this.completed.length === 3;
                },

                setStatus(text, colourClass = 'text-xs text-slate-600') {
                    this.statusText = text;
                    this.statusColour = colourClass;
                },

                async toggleRecord() {
                    this.error = '';

                    // stopping
                    if (this.recording) {
                        if (this.mediaRecorder) this.mediaRecorder.stop();
                        this.recording = false;
                        this.setStatus(
                            'Saving sample ' + this.step + '…',
                            'text-xs text-slate-600'
                        );
                        return;
                    }

                    // starting
                    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                        this.error = 'Your browser does not support audio recording.';
                        this.setStatus(this.error, 'text-xs text-rose-600');
                        return;
                    }

                    try {
                        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                        this.currentStream = stream;
                        this.chunks = [];
                        this.mediaRecorder = new MediaRecorder(stream);

                        this.mediaRecorder.ondataavailable = (e) => {
                            if (e.data && e.data.size > 0) {
                                this.chunks.push(e.data);
                            }
                        };

                        this.mediaRecorder.onstop = () => {
                            const blob = new Blob(this.chunks, { type: 'audio/webm' });

                            // stop tracks
                            if (this.currentStream) {
                                this.currentStream.getTracks().forEach(t => t.stop());
                                this.currentStream = null;
                            }

                            // preview of the last take
                            if (this.previewUrl) {
                                URL.revokeObjectURL(this.previewUrl);
                            }
                            this.previewUrl = URL.createObjectURL(blob);

                            // convert to data URL for backend
                            const reader = new FileReader();
                            reader.onloadend = () => {
                                const dataUrl = reader.result;
                                const refName = 'sample' + this.step;
                                if (this.$refs[refName]) {
                                    this.$refs[refName].value = dataUrl;
                                }

                                if (!this.completed.includes(this.step)) {
                                    this.completed.push(this.step);
                                }

                                if (this.step < 3) {
                                    const next = this.step + 1;
                                    this.setStatus(
                                        'Sample ' + this.step + ' saved. Tap “Start recording” again for sample ' + next + ' of 3.',
                                        'text-xs text-emerald-700'
                                    );
                                    this.step = next;
                                    this.previewUrl = null; // reset preview for next sample
                                } else {
                                    this.setStatus(
                                        'All 3 samples recorded. Tap “Save voice pass” to finish.',
                                        'text-xs text-emerald-700'
                                    );
                                }
                            };
                            reader.readAsDataURL(blob);
                        };

                        this.mediaRecorder.start();
                        this.recording = true;
                        this.setStatus(
                            'Recording sample ' + this.step + ' of 3… tap “Stop” when you’re done.',
                            'text-xs text-indigo-700'
                        );
                    } catch (e) {
                        console.error(e);
                        this.error = 'Could not access microphone. Please check permissions.';
                        this.setStatus(this.error, 'text-xs text-rose-600');
                    }
                },

                onSubmit(e) {
                    this.error = '';
                    if (!this.allDone) {
                        e.preventDefault();
                        this.error = 'Please record all 3 samples before saving.';
                        this.setStatus(this.error, 'text-xs text-rose-600');
                        return;
                    }
                    this.saving = true;
                }
            }
        }
    </script>
</x-app-layout>
