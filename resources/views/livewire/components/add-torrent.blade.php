<?php

use Livewire\WithFileUploads;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new class extends Component
{

	use WithFileUploads;

    #[Validate('required|file|mimes:torrent|max:2048')] // 1MB Max
    public $file;

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
		if($this->file) {
			$relativeFilePath = str_replace($this->file->getPath(), "", $this->file->getRealPath());
			$blackholeResult = app("BlackholeManager")->addTorrent("livewire-tmp" . $relativeFilePath, $this->file->getClientOriginalName());

			if($blackholeResult) {
				$this->dispatch('saved');
				$this->file = null;
				$this->fileFieldId = rand();
			} else {
				$this->addError('file', 'Adding Torrent failed!');
			}
		}
    }


}; ?>

<section>
    <form wire:submit="save" class="mt-4 space-y-4">
        <div>
            <x-file-input wire:model="file" name="file" id="upload{{ $fileFieldId }}" class="mt-1 block w-full h-10" required />
            <x-input-error class="mt-2" :messages="$errors->get('file')" />
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Add') }}</x-primary-button>

            <x-action-message class="me-3" on="saved">
                {{ __('Added.') }}
            </x-action-message>
        </div>
    </form>
</section>
