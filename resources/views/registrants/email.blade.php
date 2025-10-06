<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Email registrants — {{ $event->name }}
            </h2>
            <a href="{{ route('events.registrants', $event) }}" class="text-sm text-gray-600 hover:text-gray-800 underline">
                Back to registrants
            </a>
        </div>
    </x-slot>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if (session('success'))
            <div class="mb-4 p-3 rounded-lg bg-green-50 text-green-700 border border-green-200">
                {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="mb-4 p-3 rounded-lg bg-amber-50 text-amber-800 border border-amber-200">
                {{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-rose-50 text-rose-700 border border-rose-200">
                <ul class="list-disc pl-5">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
            <p class="text-sm text-gray-600 mb-4">
                Sending to <strong>{{ $count }}</strong> registrant{{ $count === 1 ? '' : 's' }}.
            </p>

            <form method="POST" action="{{ route('events.registrants.email.send', $event) }}">
                @csrf
                <div class="space-y-5">
                    {{-- Subject --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Subject</label>
                        <input type="text" name="subject" value="{{ old('subject') }}"
                            class="mt-1 w-full rounded-lg border-gray-300 focus:ring-2 focus:ring-indigo-500" required>
                    </div>

                    {{-- Message (Quill Editor) --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>

                        <div class="border border-gray-300 rounded-lg overflow-hidden shadow-sm mb-2">
                            {{-- Quill toolbar --}}
                            <div id="email-toolbar" class="border-b bg-gray-50 px-2 py-1 text-sm">
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

                            {{-- Hidden fields (for submission) --}}
                            <input type="hidden" name="message" id="email-html" value="{{ old('message') }}">
                            <input type="hidden" name="message_plain" id="email-plain" value="{{ old('message_plain') }}">

                            {{-- Quill editor container --}}
                            <div id="email-editor" class="min-h-[200px] bg-white overflow-y-auto"></div>
                        </div>

                        <p class="text-xs text-gray-500 mt-1">
                            Format text, add links and lists. Both HTML and plain text versions will be sent.
                        </p>
                    </div>
                </div>

                {{-- Buttons --}}
                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('events.registrants', $event) }}" class="text-sm text-gray-600 hover:text-gray-800">Cancel</a>
                    <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-xl bg-indigo-600 text-white font-medium hover:bg-indigo-700">
                        Send email
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Quill CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">

    {{-- Quill Styles --}}
    <style>
        #email-editor .ql-editor {
            min-height: 180px;
            max-height: 400px;
            padding: 0.75rem 1rem;
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

    {{-- Init Quill --}}
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toolbar = document.getElementById('email-toolbar');
            const editor = document.getElementById('email-editor');
            const htmlInput = document.getElementById('email-html');
            const plainInput = document.getElementById('email-plain');

            if (!toolbar || !editor || !htmlInput || !plainInput) return;

            const quill = new Quill(editor, {
                theme: 'snow',
                placeholder: 'Write your message…',
                modules: { toolbar: toolbar },
            });

            // Prefill existing HTML content
            const existing = htmlInput.value || '';
            if (existing.trim() !== '') {
                quill.clipboard.dangerouslyPasteHTML(existing);
            }

            // Convert HTML to plain text
            function htmlToPlain(html) {
                const div = document.createElement('div');
                div.innerHTML = html;
                return div.textContent || div.innerText || '';
            }

            // Sync to hidden fields
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
