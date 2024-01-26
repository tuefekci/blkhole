<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Browser Protocol Handler') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update the default Browser Protocol Handler for Magnet links.") }}
        </p>
    </header>


    <div class="mt-6 space-y-6">
        <div class="flex items-center gap-4">
            <x-primary-button id="registerMagnetHandlerBtn">{{ __('Register Magnet Protocol Handler') }}</x-primary-button>
        </div>
    </div>
</section>


<script type="text/javascript">
    // Function to register the magnet protocol handler
    function registerMagnetHandler() {
        console.log("registerMagnetHandler");
        if (window.navigator.registerProtocolHandler) {
            var handlerUrl = '{{url('/')}}/handle-magnet-protocol?magnet=%s';
            window.navigator.registerProtocolHandler(
                'magnet',
                handlerUrl,
                'blkhole magnet handler'
            );
        } else {
            console.log('Browser does not support protocol handlers');
        }
    }

    // Attach an event listener to the button
    document.getElementById('registerMagnetHandlerBtn').addEventListener('click', function() {
        registerMagnetHandler();
    });
</script>