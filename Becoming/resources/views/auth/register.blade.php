<x-guest-layout>
    <div class="mb-8">
        <h2 class="text-2xl font-semibold tracking-tight text-[#2B3E51]">{{ __('Create your account') }}</h2>
        <p class="mt-1 text-sm text-[#2B3E51]/70">{{ __('Join and start becoming today.') }}</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" class="!text-[#2B3E51]" />
            <x-text-input id="name" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" class="!text-[#2B3E51]" />
            <x-text-input id="email" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" class="!text-[#2B3E51]" />
            <x-text-input id="password" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" class="!text-[#2B3E51]" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full !rounded-xl !border-[#2B3E51]/20 !bg-white/70 !text-[#2B3E51] placeholder:text-[#2B3E51]/40 focus:!border-[#377991] focus:!ring-[#377991]/30" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center !rounded-xl bg-gradient-to-r from-[#377991] to-[#5DA068] px-4 py-2.5 !text-sm !font-semibold !tracking-normal !normal-case text-white shadow-sm hover:from-[#2B3E51] hover:to-[#377991] focus:ring-2 focus:!ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
            {{ __('Register') }}
        </x-primary-button>

        <p class="text-center text-sm text-[#2B3E51]/70">
            {{ __('Already registered?') }}
            <a class="font-medium text-[#377991] underline underline-offset-4 hover:text-[#2B3E51] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5] rounded-lg" href="{{ route('login') }}">
                {{ __('Log in') }}
            </a>
        </p>
    </form>
</x-guest-layout>
