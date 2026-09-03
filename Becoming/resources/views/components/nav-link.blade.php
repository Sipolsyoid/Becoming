@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[#377991] text-sm font-medium leading-5 text-[#2B3E51] focus:outline-none focus:border-[#2B3E51] transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[#2B3E51]/70 hover:text-[#2B3E51] hover:border-[#2B3E51]/20 focus:outline-none focus:text-[#2B3E51] focus:border-[#2B3E51]/20 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
