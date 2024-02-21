<?php
    use Illuminate\Support\Facades\File;
    $logFiles = File::files(storage_path('logs'));

    if (!empty($logFiles)) {

        // Get the file size
        $fileSize = File::size($logFiles[0]->getPathname());

        // Set the offset to read the last 200 lines
        $offset = max(0, $fileSize - 200);

        // Read the last 200 lines from the file
        $logLines = File::lines($logFiles[0]->getPathname());
        $logContent = array_slice($logLines->toArray(), -200);
    }
?>



<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Logs') }}
        </h2>
    </x-slot>

    <div class="flex max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <div class="flex-none w-64 h-14">
            <ul class="w-48 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @foreach($logFiles as $logFile)
                    <li class="w-full px-4 py-2 border-b border-gray-200 dark:border-gray-600">
                        {{ trim($logFile->getFilename()) }}
                        <!-- You can also display other file information like size, modification time, etc. -->
                    </li>
                @endforeach
            </ul>
        </div>
        <div class="pl-4 flex-auto">

                @if ($logContent)
                    <pre class="">
                        @foreach($logContent as $logLine)
                            {{ $logLine }}
                        @endforeach
                    </pre>
                @else
                    <p>No log file found.</p>
                @endif

        </div>
    </div>


</x-app-layout>

