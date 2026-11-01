<button {{ $attributes->merge(['type' => 'button', 'class' => 'spms-button spms-button-secondary inline-flex items-center justify-center']) }}>
    {{ $slot }}
</button>
