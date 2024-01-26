@props(['disabled' => false])

<input 
	{{ $disabled ? 'disabled' : '' }} 
	{!! $attributes->merge(['class' => 'border file:border border-gray-300 dark:border-gray-700 file:border-gray-300 dark:file:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:file:border-indigo-500 dark:focus:file:border-indigo-600 focus:ring-indigo-500 file:focus:ring-indigo-500 dark:focus:ring-indigo-600 file:dark:focus:ring-indigo-600 rounded-md shadow-sm file:py-[0.4rem]']) !!}
	type="file"
>

