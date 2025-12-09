{{-- resources/views/profile/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Profile
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

            {{-- Update Profile Info --}}
            <div class="form-card shadow-sm">
                <h3 class="form-section-title">Profile information</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Update your account name, email and basic information.
                </p>

                <div class="mt-6">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Update Password --}}
            <div class="form-card shadow-sm">
                <h3 class="form-section-title">Update password</h3>
                <p class="mt-1 text-sm text-slate-600">
                    Ensure your account is using a strong, secure password.
                </p>

                <div class="mt-6">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Delete User --}}
            <div class="form-card shadow-sm border-rose-200">
                <h3 class="form-section-title text-rose-700">Delete account</h3>
                <p class="mt-1 text-sm text-rose-600">
                    Permanently delete your account and all associated data.
                </p>

                <div class="mt-6">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
