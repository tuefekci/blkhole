@props(['disabled' => false, 'min' => null, 'max' => null, 'step' => null])

<?php
	$randomValueId = Str::random(8);
	$value = $attributes->get('value');
?>

<div {!! $attributes->merge(['class' => 'flex items-center justify-between']) !!}>
	<div class="flex-grow w-full ">
		<input 
			{{ $disabled ? 'disabled' : '' }} 
			@if ($min) min="{{$min}}" @endif
			@if ($max) max="{{$max}}" @endif
			@if ($step) step="{{$step}}" @endif
			class="w-full h-3 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
			type="range"
			value="{{$value}}"

			oninput='document.getElementById("{{$randomValueId}}").innerHTML = this.value; console.log("{{$randomValueId}}");'
		>
	</div>
	<div class="flex-shrink">
		<span id="{{$randomValueId}}" class="block w-8 text-right align-middle text-gray-500 dark:text-gray-400">{{ $value }}</span>
	</div>
</div>
