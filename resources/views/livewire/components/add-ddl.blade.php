<?php

use Livewire\Attributes\Validate; 
use Livewire\Volt\Component;

new class extends Component
{

    /**
     * Mount the component.
     */
    public function mount(): void
    {
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function add(): void
    {
        $this->dispatch('added');
    }


}; ?>

<section>
    <form wire:submit="add" class="mt-4 space-y-4">
        <div>
            <x-text-input wire:model="token" id="token" name="token" type="text" class="mt-1 block w-full" placeholder="https://" required />
            <x-input-error class="mt-2" :messages="$errors->get('token')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Add') }}</x-primary-button>

            <x-action-message class="me-3" on="added">
                {{ __('Added.') }}
            </x-action-message>
        </div>
    </form>
</section>
