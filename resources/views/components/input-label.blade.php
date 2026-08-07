@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-spms-ink']) }}>
    {{ $value ?? $slot }}
</label>
