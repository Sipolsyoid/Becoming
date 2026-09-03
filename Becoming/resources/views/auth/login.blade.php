<x-guest-layout>
    <x-auth-session-status class="mb-6 rounded-xl bg-[#5DA068]/10 px-4 py-3" :status="session('status')" />

    <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-[#2B3E51]">{{ __('Welcome back') }}</h2>
        <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('Log in to continue your journey.') }}</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-[#2B3E51]" />
            <x-text-input id="email" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" class="!text-[#2B3E51]" />
            <x-text-input id="password" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-[#2B3E51]/20 text-[#377991] shadow-sm focus:ring-[#377991]/30" name="remember">
                <span class="ms-2 text-sm text-[#2B3E51]/75">{{ __('Remember me') }}</span>
            </label>
        </div>

        <x-primary-button class="w-full justify-center !rounded-xl bg-gradient-to-r from-[#377991] to-[#5DA068] px-4 py-2.5 !text-sm !font-semibold !tracking-normal !normal-case text-white shadow-sm hover:from-[#2B3E51] hover:to-[#377991] focus:ring-2 focus:!ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
            {{ __('Log in') }}
        </x-primary-button>

        @if (Route::has('register'))
            <p class="text-center text-sm text-[#2B3E51]/70">
                {{ __("Don't have an account?") }}
                <a class="font-medium text-[#377991] underline underline-offset-4 hover:text-[#2B3E51] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5] rounded-lg" href="{{ route('register') }}">
                    {{ __('Register') }}
                </a>
            </p>
        @endif
    </form>
</x-guest-layout>
