@props(['tone'=>'neutral','variant'=>'soft','pill'=>false])
<span {{ $attributes->merge(['class'=>'spms-badge'.($pill ? ' is-pill' : '')]) }} data-tone="{{ $tone }}" data-variant="{{ $variant }}">{{ $slot }}</span>
