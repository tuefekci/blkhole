<?php

use App\Models\Setting;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate; 
use Livewire\Volt\Component;

new class extends Component
{
    #[Validate('required')] 
    public $parallel;

    #[Validate('required')] 
    public $connections;

    #[Validate('required')] 
    public $bandwidth;

    #[Validate('required')] 
    public $chunkSize;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->parallel = Setting::get('parallel');
        $this->connections = Setting::get('connections');
        $this->bandwidth = Setting::get('bandwidth') / 1024 / 1024;
        $this->chunkSize = Setting::get('chunkSize') / 1024 / 1024;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateSettings(): void
    {
        Setting::updateOrCreate(['key' => 'parallel'], ['value' => $this->parallel]);
        Setting::updateOrCreate(['key' => 'connections'], ['value' => $this->connections]);
        Setting::updateOrCreate(['key' => 'bandwidth'], ['value' => $this->bandwidth * 1024 * 1024]);
        Setting::updateOrCreate(['key' => 'chunkSize'], ['value' => $this->chunkSize * 1024 * 1024]);

        $this->dispatch('settings-updated');
    }


}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Settings') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update the BlkHole Settings") }}
        </p>
    </header>


    <form wire:submit="updateSettings" class="mt-6 space-y-6">
        <div>
            <x-input-label for="parallel" :value="__('Number of Parallel Downloads')" />
            <x-slider-input wire:model="parallel" id="parallel" name="parallel" class="mt-1 block w-full" required min="1" max="16" step="1" :value="$parallel"/>
            <x-input-error class="mt-2" :messages="$errors->get('parallel')" />
        </div>

        <div>
            <x-input-label for="connections" :value="__('Number of Connections per Download')" />
            <x-slider-input wire:model="connections" id="connections" name="connections" class="mt-1 block w-full" required min="1" max="16" step="1" :value="$connections"/>
            <x-input-error class="mt-2" :messages="$errors->get('connections')" />
        </div>

        <div>
            <x-input-label for="bandwidth" :value="__('Max bandwidth in MB/s')" />
            <x-text-input wire:model="bandwidth" id="bandwidth" name="bandwidth" type="number" class="mt-1 block w-full" min="0.1" step="0.1" required />
            <x-input-error class="mt-2" :messages="$errors->get('bandwidth')" />
        </div>

        <div>
            <x-input-label for="chunkSize" :value="__('Chunk size in MB')" />
            <x-text-input wire:model="chunkSize" id="chunkSize" name="chunkSize" type="number" class="mt-1 block w-full" min="0.1" step="0.1" required />
            <x-input-error class="mt-2" :messages="$errors->get('chunkSize')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="settings-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
