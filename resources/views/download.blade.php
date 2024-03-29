<?php

use App\Models\Download;

    try {
        // Retrieve the download with the given ID
        $download = Download::findOrFail($id);
    } catch (ModelNotFoundException $exception) {
        // Model not found, throw 404 error
        abort(404);
    }

?>

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-nowrap">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __($download->src_type).": ".$download->name }}
            </h2>
            <div class="grow flex justify-end">

                <div class="grid grid-cols-2 gap-1">
                    

                    <button type="button" wire:click="pauseDownload({{ $download->id }})" title="{{ $download->paused ? __('Continue Download') : __('Pause Download') }}"> 
                        @if($download->paused)
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer">
                                <path d="M21.4086 9.35258C23.5305 10.5065 23.5305 13.4935 21.4086 14.6474L8.59662 21.6145C6.53435 22.736 4 21.2763 4 18.9671L4 5.0329C4 2.72368 6.53435 1.26402 8.59661 2.38548L21.4086 9.35258Z"/>
                            </svg>
                        @else
                            <svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer">
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM8.07612 8.61732C8 8.80109 8 9.03406 8 9.5V14.5C8 14.9659 8 15.1989 8.07612 15.3827C8.17761 15.6277 8.37229 15.8224 8.61732 15.9239C8.80109 16 9.03406 16 9.5 16C9.96594 16 10.1989 16 10.3827 15.9239C10.6277 15.8224 10.8224 15.6277 10.9239 15.3827C11 15.1989 11 14.9659 11 14.5V9.5C11 9.03406 11 8.80109 10.9239 8.61732C10.8224 8.37229 10.6277 8.17761 10.3827 8.07612C10.1989 8 9.96594 8 9.5 8C9.03406 8 8.80109 8 8.61732 8.07612C8.37229 8.17761 8.17761 8.37229 8.07612 8.61732ZM13.0761 8.61732C13 8.80109 13 9.03406 13 9.5V14.5C13 14.9659 13 15.1989 13.0761 15.3827C13.1776 15.6277 13.3723 15.8224 13.6173 15.9239C13.8011 16 14.0341 16 14.5 16C14.9659 16 15.1989 16 15.3827 15.9239C15.6277 15.8224 15.8224 15.6277 15.9239 15.3827C16 15.1989 16 14.9659 16 14.5V9.5C16 9.03406 16 8.80109 15.9239 8.61732C15.8224 8.37229 15.6277 8.17761 15.3827 8.07612C15.1989 8 14.9659 8 14.5 8C14.0341 8 13.8011 8 13.6173 8.07612C13.3723 8.17761 13.1776 8.37229 13.0761 8.61732Z"/>
                            </svg>
                        @endif
                    </button>

                    <button type="button" wire:click="openDeleteModal({{ $download->id }})" title="{{ __('Delete Download') }}" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer"> 
                        <svg width="24px" height="24px" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
                            <path d="M512 64a448 448 0 1 1 0 896 448 448 0 0 1 0-896zM288 512a38.4 38.4 0 0 0 38.4 38.4h371.2a38.4 38.4 0 0 0 0-76.8H326.4A38.4 38.4 0 0 0 288 512z"/>
                        </svg>
                    </button>
                </div>

            </div>
        </div>
    </x-slot>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700 pb-2">
                    {{ __("Details") }}
                </div>

                <div class="pt-2">

                    <div class="grid grid-cols-4 gap-1">

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Status") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400">{{ __(Download::getStatusAsString($download->status)) }}</div>
                        </div>
                    
                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Paused") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400">{{ $download->paused ? __('Yes') : __('No') }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Created") }}</div>
                            <div class="font-light text-gray-500 dark:text-gray-400">{{ $download->created_at }}</div>
                        </div>
                        
                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Updated") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400">{{ $download->updated_at }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Debird Provider") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400">{{ __($download->debrid_provider) }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Debrid Id") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400">{{ $download->debrid_id }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Source Type") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400 ">{{ __($download->src_type) }}</div>
                        </div>

                        <div class="pt-2">
                            <div class="pb-1 font-semibold leading-none text-gray-900 dark:text-white">{{ __("Source Path") }}</div>
                            <div class=" font-light text-gray-500 dark:text-gray-400 truncate" title="{{ $download->src_path }}">{{ $download->src_path }}</div>
                        </div>

                    </div>

                </div>


            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700 pb-2">
                    {{ __("Files") }}
                </div>
            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700 pb-2">
                    {{ __("Jobs") }}
                </div>
            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                    {{ __("Status History") }}
                </div>

                <div class="relative max-h-96 overflow-x-auto pt-2">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                <th scope="col" class="px-6 py-3">
                                    {{ __("Date") }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ __("From") }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ __("To") }}
                                </th>
                                <th scope="col" class="px-6 py-3">
                                    {{ __("Comment") }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="table-auto overflow-scroll">
                        @foreach($download->status()->history()->orderByDesc('created_at')->get() as $stateHistory)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <td class="px-5 py-3 text-sm font-medium">
                                    {{ $stateHistory->created_at }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500">
                                    {{ __(Download::getStatusAsString($stateHistory->from)) ?? __('N/A') }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500">
                                    {{ __(Download::getStatusAsString($stateHistory->to)) }}
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500">
                                    {{ __($stateHistory->getCustomProperty('comments')) ?? __('N/A') }}
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                
            </div>
        </div>
    </div>



</x-app-layout>

