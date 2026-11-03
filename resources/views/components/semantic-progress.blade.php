@props(['value'=>0,'max'=>100,'label'=>'Progress','tone'=>'primary','busy'=>false])
@php($percent = $max > 0 ? min(100,max(0,100*$value/$max)) : 0)
<div {{ $attributes->merge(['class'=>'spms-progress-group']) }} data-tone="{{ $tone }}">
    <p>{{ $label }} <span>{{ $value }} / {{ $max }}</span></p>
    <div class="spms-progress" role="progressbar" aria-label="{{ $label }}" aria-valuemin="0" aria-valuemax="{{ max(1,$max) }}" aria-valuenow="{{ min(max(1,$max),max(0,$value)) }}" aria-valuetext="{{ $value }} of {{ $max }}{{ $value>$max ? '; above commitment' : '' }}" @if($busy) aria-busy="true" @endif><span class="spms-progress-fill" style="width:{{ round($percent,2) }}%"></span></div>
</div>
