<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-lg border border-transparent bg-spms-navy px-5 py-3 text-sm font-semibold text-white shadow-sm transition ease-in-out duration-150 hover:bg-spms-navy-hover focus:outline-none focus:ring-2 focus:ring-spms-sky focus:ring-offset-2 active:bg-spms-navy-hover']) }}>
    {{ $slot }}
</button>
