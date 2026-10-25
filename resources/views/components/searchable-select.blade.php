@props(['id', 'name', 'options' => [], 'selected' => '', 'placeholder' => 'Search and select...'])

<div
    x-data="searchableSelect({{ Illuminate\Support\Js::from($options) }}, {{ Illuminate\Support\Js::from((string) $selected) }})"
    class="relative mt-1"
    @click.outside="open = false; restoreLabel()"
>
    <input type="hidden" name="{{ $name }}" :value="value">
    <div class="relative">
        <input
            id="{{ $id }}"
            type="search"
            x-model="search"
            @focus="open = true"
            @input="onInput"
            @keydown.escape.prevent="open = false; restoreLabel()"
            @keydown.arrow-down.prevent="open = true"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            {{ $attributes->merge(['class' => 'block w-full rounded-lg border-gray-300 pr-10 shadow-sm focus:border-spms-sky focus:ring-spms-sky']) }}
            role="combobox"
            :aria-expanded="open"
            aria-autocomplete="list"
        >
        <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-spms-ink" @click="value ? clear() : (open = !open)" :aria-label="value ? 'Clear selection' : 'Open options'">
            <span x-show="value" class="text-lg leading-none">&times;</span>
            <svg x-show="!value" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>
    <div x-cloak x-show="open" x-transition.opacity.duration.100ms class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg" role="listbox">
        <button type="button" class="block w-full px-3 py-2 text-left text-sm text-gray-500 hover:bg-gray-50" @mousedown.prevent="clear()">Unassigned</button>
        <template x-for="option in filteredOptions" :key="option.value">
            <button type="button" class="block w-full px-3 py-2 text-left text-sm hover:bg-blue-50 hover:text-spms-navy" :class="String(option.value) === value ? 'bg-blue-50 font-semibold text-spms-navy' : 'text-gray-700'" @mousedown.prevent="select(option)" role="option" :aria-selected="String(option.value) === value">
                <span x-text="option.label"></span>
            </button>
        </template>
        <p x-show="filteredOptions.length === 0" class="px-3 py-3 text-sm text-gray-500">No matching results.</p>
    </div>
</div>
