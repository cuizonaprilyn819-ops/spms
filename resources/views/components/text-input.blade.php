@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-lg border-gray-300 shadow-sm focus:border-spms-sky focus:ring-spms-sky']) }}>
