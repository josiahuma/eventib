<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Email registrants — {{ $event->name }}
            </h2>
            <a href="{{ route('events.registrants', $event) }}"
               class="text-sm text-slate-600 hover:text-slate-900 underline">
                Back to registrants
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Success --}}
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-emerald-50 text-emerald-700 border border-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Errors --}}
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)
                        <li>{{ $e }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="form-card shadow-sm">

            <p class="text-sm text-slate-600 mb-3">
                Sending to <strong>{{ $count }}</strong> registrant{{ $count === 1 ? '' : 's' }}.
            </p>

            <form method="POST" action="{{ route('events.registrants.email.send', $event) }}" class="space-y-6">
                @csrf

                {{-- Subject --}}
                <div>
                    <label class="form-label">Subject</label>
                    <input type="text"
                           name="subject"
                           value="{{ old('subject') }}"
                           class="form-input"
                           required>
                </div>

                {{-- Message / Quill --}}
                <div>
                    <label class="form-label mb-1">Message</label>

                    <div class="border border-slate-300 rounded-md shadow-sm overflow-hidden">

                        {{-- Quill toolbar --}}
                        <div id="email-toolbar" class="border-b bg-slate-50 px-2 py-1 text-sm">
                            <span class="ql-formats">
                                <button class="ql-bold"></button>
                                <button class="ql-italic"></button>
                                <button class="ql-underline"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-list" value="ordered"></button>
                                <button class="ql-list" value="bullet"></button>
                            </span>
                            <span class="ql-formats">
                                <button class="ql-link"></button>
                                <button class="ql-blockquote"></button>
                            </span>
                        </div>

                        {{-- Hidden submission fields --}}
                        <input type="hidden" name="message" id="email-html" value="{{ old('message') }}">
                        <input type="hidden" name="message_plain" id="email-plain" value="{{ old('message_plain') }}">

                        {{-- Editor --}}
                        <div id="email-editor"
                             class="min-h-[220px] bg-white overflow-y-auto"></div>
                    </div>

                    <p class="form-help mt-2">
                        Format text, add links and lists. Both HTML and plain text versions will be sent.
                    </p>
                </div>

                {{-- Buttons --}}
                <div class="pt-4 flex items-center justify-end gap-3">
                    <a href="{{ route('events.registrants', $event) }}"
                       class="text-sm text-slate-600 hover:text-slate-900">
                        Cancel
                    </a>

                    <button type="submit" class="form-primary-btn">
                        Send email
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Quill CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">

    {{-- Quill custom styles --}}
    <style>
        #email-editor .ql-editor {
            min-height: 200px;
            max-height: 450px;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            color: #111827;
        }
        #email-editor .ql-editor.ql-blank::before {
            color: #9ca3af;
            font-style: italic;
            content: attr(data-placeholder);
        }
    </style>

    {{-- Quill JS --}}
    <script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

    {{-- Quill initialisation --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toolbar = document.getElementById('email-toolbar');
            const editor = document.getElementById('email-editor');
            const htmlInput = document.getElementById('email-html');
            const plainInput = document.getElementById('email-plain');

            const quill = new Quill(editor, {
                theme: 'snow',
                placeholder: 'Write your message…',
                modules: { toolbar }
            });

            // Prefill existing HTML (edit case)
            if (htmlInput.value.trim() !== '') {
                quill.clipboard.dangerouslyPasteHTML(htmlInput.value);
            }

            function htmlToPlain(html) {
                const div = document.createElement('div');
                div.innerHTML = html;
                return div.textContent || div.innerText || '';
            }

            function syncFields() {
                const html = quill.root.innerHTML.trim();
                htmlInput.value = html;
                plainInput.value = htmlToPlain(html);
            }

            quill.on('text-change', syncFields);
            document.querySelector('form').addEventListener('submit', syncFields);
        });
    </script>
</x-app-layout>
