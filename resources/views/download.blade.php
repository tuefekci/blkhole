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
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __($download->src_type).": ".$download->name }}
        </h2>
    </x-slot>




</x-app-layout>

