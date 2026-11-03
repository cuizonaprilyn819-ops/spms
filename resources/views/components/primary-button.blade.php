<button {{ $attributes->merge(['type' => 'submit', 'class' => 'spms-button spms-button-primary inline-flex items-center justify-center']) }}>
    {{ $slot }}
</button>
