@props(['tone'=>'info','dismissible'=>false])
<div {{ $attributes->merge(['class'=>'spms-alert']) }} data-tone="{{ $tone }}" role="{{ $tone==='danger' ? 'alert' : 'status' }}" @if($dismissible) x-data="{ visible: true }" x-show="visible" @endif>
    <div>{{ $slot }}</div>
    @if($dismissible)<button type="button" class="spms-alert-dismiss" data-action-plain @click="visible=false" aria-label="Dismiss message">×</button>@endif
</div>
