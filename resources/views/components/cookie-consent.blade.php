{{-- resources/views/components/cookie-consent.blade.php --}}
<div
    x-data="cookieBanner({
        key: 'cookie-pref-v1',
        // Optional: pass GA ID via config('services.analytics.ga_id')
        gaId: '{{ config('services.analytics.ga_id') ?? '' }}',
        policyUrl: '{{ route('about') ?? url('/about') }}'
    })"
    x-init="init()"
    x-show="open"
    x-cloak
    class="fixed inset-x-0 bottom-0 z-50"
    aria-live="polite"
>
    <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 pb-4">
        <div
            class="rounded-2xl border border-gray-200 bg-white shadow-lg"
            role="dialog"
            aria-label="Cookie preferences"
        >
            <div class="p-4 sm:p-5">
                <div class="sm:flex sm:items-start sm:justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">We use cookies</h3>
                        <p class="mt-1 text-sm text-gray-600">
                            We use essential cookies to make Eventib work. With your permission, we’ll also use analytics cookies
                            to help us improve the product. See our <a href="{{ route('about') ?? url('/about') }}" class="underline">policy</a>.
                        </p>
                        <div x-show="showPrefs" class="mt-3 rounded-lg border bg-gray-50 p-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900">Essential</div>
                                    <div class="text-gray-600">Always on – required for core features.</div>
                                </div>
                                <input type="checkbox" checked disabled class="rounded border-gray-300">
                            </div>
                            <hr class="my-3">
                            <div class="flex items-center justify-between">
                                <div class="text-sm">
                                    <div class="font-medium text-gray-900">Analytics</div>
                                    <div class="text-gray-600">Helps us understand usage (e.g. page views).</div>
                                </div>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" x-model="prefs.analytics" class="rounded border-gray-300">
                                    <span class="text-sm text-gray-700" x-text="prefs.analytics ? 'On' : 'Off'"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 sm:mt-0 flex shrink-0 items-center gap-2">
                        <button type="button"
                                @click="reject()"
                                class="inline-flex items-center rounded-lg border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Reject non-essential
                        </button>
                        <button type="button"
                                @click="acceptAll()"
                                class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Accept all
                        </button>
                        <button type="button"
                                @click="showPrefs = !showPrefs"
                                class="hidden sm:inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Preferences
                        </button>
                    </div>
                </div>

                <div class="mt-3 sm:hidden">
                    <button type="button"
                            @click="showPrefs = !showPrefs"
                            class="w-full rounded-lg border px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Preferences
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('cookieBanner', (cfg) => ({
            key: cfg.key || 'cookie-pref-v1',
            gaId: cfg.gaId || '',
            policyUrl: cfg.policyUrl || '/about',
            open: false,
            showPrefs: false,
            prefs: { necessary: true, analytics: false, ts: null },

            init() {
                try {
                    const stored = localStorage.getItem(this.key);
                    if (stored) {
                        this.prefs = JSON.parse(stored);
                        this.open = false;
                        this.afterSet(); // ensure scripts load if allowed
                    } else {
                        this.open = true;
                    }
                } catch (_) { this.open = true; }
            },

            acceptAll() {
                this.prefs = { necessary: true, analytics: true, ts: Date.now() };
                this.persistAndClose();
            },

            reject() {
                this.prefs = { necessary: true, analytics: false, ts: Date.now() };
                this.persistAndClose();
            },

            persistAndClose() {
                localStorage.setItem(this.key, JSON.stringify(this.prefs));
                // also a cookie so the server can read it if needed
                document.cookie = this.key + '=' + encodeURIComponent(JSON.stringify(this.prefs))
                    + '; Path=/; Max-Age=' + (60*60*24*180) + '; SameSite=Lax';
                this.open = false;
                this.afterSet();
            },

            afterSet() {
                // Load GA only if consented and an ID is configured
                if (this.prefs.analytics && this.gaId && !window._gaLoaded) {
                    window._gaLoaded = true;
                    const s1 = document.createElement('script');
                    s1.async = true;
                    s1.src = 'https://www.googletagmanager.com/gtag/js?id=' + this.gaId;
                    document.head.appendChild(s1);

                    const s2 = document.createElement('script');
                    s2.innerHTML = `
                        window.dataLayer = window.dataLayer || [];
                        function gtag(){dataLayer.push(arguments);}
                        gtag('js', new Date());
                        gtag('config', '${this.gaId}', { anonymize_ip: true });
                    `;
                    document.head.appendChild(s2);
                }
            }
        }))
    })
    </script>
</div>