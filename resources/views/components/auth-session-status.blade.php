@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'spms-alert font-medium text-sm']) }} data-tone="success" role="status">
        {{ $status }}
    </div>
@endif
