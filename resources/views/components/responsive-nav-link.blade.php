@props(['active'])

@php
$section = basename(parse_url($attributes->get('href', ''), PHP_URL_PATH) ?? '');
$icon = match ($section) {
    'employees' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M22 21v-2a4 4 0 0 0-3-3.87M15 3.13a4 4 0 0 1 0 7.75M13 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0',
    'notifications' => 'M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
    'offices' => 'M3 21h18M5 21V3h14v18M9 7h1M14 7h1M9 11h1M14 11h1M10 21v-5h4v5',
    'evaluation-cycles' => 'M8 2v4M16 2v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2M8 14h2M14 14h2',
    'office-targets', 'performance-targets' => 'M22 12a10 10 0 1 1-10-10M18 12a6 6 0 1 1-6-6M12 12 22 2M16 2h6v6',
    'accomplishments', 'evaluations', 'ratings' => 'M9 12l2 2 4-4M9 3H5v18h14V3h-4M9 2h6v4H9z',
    'movs' => 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zM14 2v6h6M8 13h8M8 17h5',
    'reports', 'target-documents' => 'M4 3h16v18H4zM8 7h8M8 11h8M8 15h5',
    'planning' => 'M12 3v6M5 15v-3h14v3M3 15h4v6H3zM10 3h4M10 15h4v6h-4zM17 15h4v6h-4zM12 12v3',
    default => 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
};
$classes = ($active ?? false)
            ? 'spms-nav-link is-active block w-full rounded-xl px-3.5 py-3.5 text-start text-sm font-semibold text-white transition focus:outline-none focus:ring-2 focus:ring-spms-sky'
            : 'spms-nav-link block w-full rounded-xl px-3.5 py-3.5 text-start text-sm font-medium text-white/80 transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-spms-sky';
@endphp

<a @if($active ?? false) aria-current="page" @endif {{ $attributes->merge(['class' => $classes]) }}>
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}"/></svg>
    <span class="min-w-0 flex-1">{{ $slot }}</span>
</a>
