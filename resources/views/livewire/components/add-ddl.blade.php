<?php

use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component
{
    #[Validate('required')] 
    public $url;

	public $fileFieldId;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
		$this->fileFieldId = rand();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function save(): void
    {
		if($this->url) {
			$blackholeResult = app("BlackholeManager")->addDDL($this->url);

			if($blackholeResult) {
				$this->dispatch('saved');
				$this->url = null;
				$this->fileFieldId = rand();
			} else {
				$this->addError('url', 'Adding Magnet failed!');
			}
		}
    }


}; ?>

<section>
    <form wire:submit="save" class="mt-2 space-y-4">
        <div>
            <x-text-input wire:model="url" name="url" id="ddl{{ $fileFieldId }}" class="mt-1 block w-full h-10" placeholder="https://" required />
            <x-input-error class="mt-2" :messages="$errors->get('url')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Add') }}</x-primary-button>

            <x-action-message class="me-3" on="saved">
                {{ __('Added.') }}
            </x-action-message>
        </div>
    </form>
</section>

