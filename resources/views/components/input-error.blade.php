@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'spms-validation text-sm text-red-600 space-y-1', 'role' => 'alert']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
