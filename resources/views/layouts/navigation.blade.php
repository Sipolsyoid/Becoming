<nav x-data="{ open: false }" class="bg-white/70 backdrop-blur border-b border-[#2B3E51]/10">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center">
                        <img src="{{ asset('img/big-logo2.png') }}" alt="Becoming" class="block h-9 w-auto max-w-[140px] select-none" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('progress')" :active="request()->routeIs('progress')">
                        {{ __('Progress') }}
                    </x-nav-link>
                    <x-nav-link :href="route('habits.index')" :active="request()->routeIs('habits.*')">
                        {{ __('Habits') }}
                    </x-nav-link>
                    <x-nav-link :href="route('history')" :active="request()->routeIs('history')">
                        {{ __('History') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div class="flex items-center gap-4">
                    <div class="text-sm font-medium text-[#2B3E51]/80">
                        {{ __('Welcome, :name', ['name' => Auth::user()->name]) }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center rounded-xl border border-[#2B3E51]/20 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#2B3E51]/80 shadow-sm hover:bg-white/80 hover:text-[#2B3E51] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 focus:ring-offset-2 focus:ring-offset-[#FAF8F5]">
                            {{ __('Log out') }}
                        </button>
                    </form>
                </div>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center rounded-xl p-2 text-[#2B3E51]/70 hover:bg-white/60 hover:text-[#2B3E51] focus:outline-none focus:ring-2 focus:ring-[#377991]/40 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('progress')" :active="request()->routeIs('progress')">
                {{ __('Progress') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('habits.index')" :active="request()->routeIs('habits.*')">
                {{ __('Habits') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('history')" :active="request()->routeIs('history')">
                {{ __('History') }}
            </x-responsive-nav-link>
        </div>

        <div class="pt-4 pb-1 border-t border-[#2B3E51]/10">
            <div class="px-4">
                <div class="font-medium text-base text-[#2B3E51]">
                    {{ __('Welcome, :name', ['name' => Auth::user()->name]) }}
                </div>
            </div>

            <div class="mt-3 space-y-1 px-2">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full ps-3 pe-4 py-2 text-start text-base font-medium text-[#2B3E51]/75 hover:text-[#2B3E51] hover:bg-white/60 focus:outline-none focus:text-[#2B3E51] focus:bg-white/60 transition duration-150 ease-in-out rounded-xl">
                        {{ __('Log out') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
