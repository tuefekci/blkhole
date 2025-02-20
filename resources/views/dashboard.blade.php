<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 justify-stretch items-stretch max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <div class=" text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                        {{ __("Add Magnet") }}
                    </div>
                    <livewire:components.add-magnet />
                </div>
            </div>

            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg ">
                <div class="max-w-xl">
                <div class=" text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                        {{ __("Add Torrent") }}
                    </div>
                    <livewire:components.add-torrent />
                </div>
            </div>

            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                <div class=" text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                        {{ __("Add DDL") }}
                    </div>
                    <livewire:components.add-ddl />
                </div>
            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 justify-stretch items-stretch max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <div class=" text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                        {{ __("Network Speed") }}
                    </div>
                </div>
            </div>

            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                <div class=" text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                        {{ __("Download Statistics") }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="p-2 sm:p-4 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="text-gray-900 dark:text-gray-100 border-b border-gray-300 dark:border-gray-700">
                    {{ __("Recent Downloads") }}
                </div>

                <livewire:pages.dashboard.downloads />
                
            </div>
        </div>
    </div>

</x-app-layout>
