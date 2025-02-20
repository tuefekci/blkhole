<?php

use App\Helpers\NumberHelper;
use Illuminate\Support\Facades\Cache;
//use JalalLinuX\Pm2\Pm2;

    //dump(pm2()->version());

    /*
    $services = Cache::remember('services_list', now()->addSeconds(30), function () {
        return pm2()->list();
    });*/

    $services = [];
?>

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:pages.settings.global />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:pages.settings.accounts />
                </div>
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-gray-800 shadow sm:rounded-lg">
                <div class="max-w-xl">
                    <livewire:pages.settings.protocolHandler />
                </div>
            </div>
        </div>
    </div>

    <div class="pt-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                    <caption class="p-5 text-lg font-semibold text-left rtl:text-right text-gray-900 bg-white dark:text-white dark:bg-gray-800">
                        Services
                        <p class="mt-1 text-sm font-normal text-gray-500 dark:text-gray-400">Browse a list of Services.</p>
                    </caption>
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">
                                Service
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Instances
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Uptime
                            </th>
                            <th scope="col" class="px-6 py-3">
                                CPU
                            </th>
                            <th scope="col" class="px-6 py-3">
                                Memory
                            </th>
                            <th scope="col" class="px-6 py-3">
                                <span class="sr-only">Edit</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>

                        @foreach ($services as $service)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    {{ $service->name }} ({{ $service->pid }})
                                </th>
                                <td class="px-6 py-4">
                                    {{ $service->pm2Env->instances }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ NumberHelper::humanReadableUptime( $service->pm2Env->pmUptime ) }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ $service->monit->cpu }}
                                </td>
                                <td class="px-6 py-4">
                                    {{ NumberHelper::humanReadableBytes($service->monit->memory) }}
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="#" class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>



</x-app-layout>
