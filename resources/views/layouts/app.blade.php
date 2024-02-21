<?php

    use Illuminate\Support\Facades\File;
    use Illuminate\Support\Facades\Cache;

    // Get the path to the composer.json file
    $composerJsonPath = base_path('composer.json');

    $version = "BLEEDINGEDGE";

    // Check if the file exists
    if (File::exists($composerJsonPath)) {
        // Read the contents of the composer.json file
        $composerJsonContents = File::get($composerJsonPath);

        // Decode the JSON contents into an array
        $composerData = json_decode($composerJsonContents, true);

        // Check if the version field exists in the decoded data
        if (isset($composerData['version'])) {
            // Retrieve the version field
            $version = $composerData['version'];
        }
    }  

    // Retrieve the Git commit hash and cache it for 60 minutes
    $commitHash = Cache::remember('git_commit_hash', now()->addMinutes(30), function () {
        return trim(shell_exec('git rev-parse --short=7 HEAD'));
    });
?>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900">
            <livewire:layout.navigation />

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>

            <div class="min-w-full text-center text-sm font-light text-gray-900 dark:text-gray-100 pt-4">
                Version: {{$version}} 
                <a href="https://github.com/tuefekci/blkhole/commit/{{$commitHash}}" target="_blank"><span class="text-xs">({{$commitHash}})</span></a> | 
                Github: <a target="_blank" href="https://github.com/tuefekci/blkhole">/tuefekci/blkhole</a>
            </div>
        </div>
    </body>
</html>
