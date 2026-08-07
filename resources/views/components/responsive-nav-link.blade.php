@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full rounded-r-lg border-l-4 border-spms-sky bg-blue-50 py-2.5 ps-3 pe-4 text-start text-sm font-semibold text-spms-navy transition duration-150 ease-in-out focus:border-spms-navy focus:bg-blue-50 focus:outline-none focus:text-spms-navy'
            : 'block w-full rounded-r-lg border-l-4 border-transparent py-2.5 ps-3 pe-4 text-start text-sm font-medium text-gray-600 transition duration-150 ease-in-out hover:border-gray-300 hover:bg-gray-50 hover:text-spms-ink focus:border-gray-300 focus:bg-gray-50 focus:outline-none focus:text-spms-ink';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
