<section class="form-card mt-6">
    <header class="mb-4">
        <h2 class="text-lg font-semibold text-slate-900">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-slate-600">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    {{-- Email verification form --}}
    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    {{-- Profile update form --}}
    <form method="post" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        {{-- Name --}}
        <div>
            <label for="name" class="form-label">
                {{ __('Name') }}
            </label>
            <input
                id="name"
                name="name"
                type="text"
                class="form-input"
                value="{{ old('name', $user->name) }}"
                required
                autocomplete="name"
            >
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        {{-- Email --}}
        <div>
            <label for="email" class="form-label">
                {{ __('Email') }}
            </label>
            <input
                id="email"
                name="email"
                type="email"
                class="form-input"
                value="{{ old('email', $user->email) }}"
                required
                autocomplete="username"
            >
            <x-input-error :messages="$errors->get('email')" class="mt-2" />

            {{-- Email verification reminder --}}
            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-sm text-slate-700">
                        {{ __('Your email address is unverified.') }}

                        <button
                            form="send-verification"
                            class="underline text-sm text-slate-600 hover:text-slate-900"
                        >
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm text-emerald-600 font-medium">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        {{-- Buttons --}}
        <div class="pt-2 flex items-center gap-4">
            <button class="form-primary-btn">
                {{ __('Save') }}
            </button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-emerald-600"
                >
                    {{ __('Saved.') }}
                </p>
            @endif
        </div>
    </form>
</section>
