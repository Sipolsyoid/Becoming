@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#377991] text-start text-base font-medium text-[#2B3E51] bg-[#76C7B7]/10 focus:outline-none focus:text-[#2B3E51] focus:bg-[#76C7B7]/20 focus:border-[#2B3E51] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#2B3E51]/75 hover:text-[#2B3E51] hover:bg-white/60 hover:border-[#2B3E51]/20 focus:outline-none focus:text-[#2B3E51] focus:bg-white/60 focus:border-[#2B3E51]/20 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
