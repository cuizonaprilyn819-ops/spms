<button {{ $attributes->merge(['type' => 'submit', 'class' => 'spms-button spms-button-danger inline-flex items-center justify-center']) }}>
    {{ $slot }}
</button>
