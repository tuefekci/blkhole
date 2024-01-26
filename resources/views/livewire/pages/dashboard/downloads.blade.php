<?php

use Livewire\Volt\Component;
use App\Models\Download;


new class extends Component
{

	public $downloads;
	public $deleteModal;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
		$this->refreshDownloads();
    }

	public function refreshDownloads() {
		$this->downloads = Download::latest('updated_at')->take(20)->get();
	}

	public function pauseDownload($id) {
		app("DownloadManager")->pauseDownload($id);
		$this->refreshDownloads();
	}

	public function deleteDownload($id) {
		// Delete the Download model instance
		app("DownloadManager")->deleteDownload($id);

		$this->deleteModal = false;

		// refresh the downloads after deletion
		$this->refreshDownloads();
    }

	public function openDeleteModal($id) {
		$this->deleteModal = $id;
	}

	public function closeDeleteModal() {
		$this->deleteModal = false;
	}

}; ?>



<div class="flex flex-col">
  <div class="overflow-x-auto sm:-mx-6 lg:-mx-8">
    <div class="inline-block min-w-full py-2 sm:px-6 lg:px-8">
      <div class="overflow-hidden">
        <table class="min-w-full text-center text-sm font-light text-gray-900 dark:text-gray-100" wire:poll.visible="refreshDownloads">
          <thead class="border-b bg-neutral-800 font-medium dark:border-gray-700 dark:bg-gray-900 text-left">
            <tr>
              <th scope="col" class=" px-6 py-4">Name</th>
              <th scope="col" class=" px-6 py-4">Type</th>
			  <th scope="col" class=" px-6 py-4">Status</th>
			  <th scope="col" class=" px-6 py-4">Progress</th>
			  <th scope="col" class=" px-6 py-4">Last Update</th>
              <th scope="col" class=" px-6 py-4">Actions</th>
            </tr>
          </thead>
          <tbody>

		  	@foreach($downloads as $download)
				<tr class="border-b dark:border-gray-700 text-left">
					<td class="whitespace-nowrap px-6 py-4 font-medium truncate" title="{{ $download->name }}">
						@if(strlen($download->name) > 40)
							{{ substr($download->name, 0, 40) }}...
						@else
							{{ $download->name }}
						@endif
					</td>
					<td class="whitespace-nowrap px-6 py-4">{{ __($download->src_type) }}</td>
					<td class="whitespace-nowrap px-6 py-4">{{ __(app("DownloadManager")->getStatusAsString($download->status)) }}</td>
					<td class="whitespace-nowrap px-6 py-4">
						<div class="w-full bg-neutral-200 dark:bg-neutral-600">
							<div
								class="bg-primary p-0.5 text-center text-xs font-medium leading-none text-primary-100"
								style="width: {{ __(app("DownloadManager")->getProgress($download->id)) }}%">
								{{ __(app("DownloadManager")->getProgress($download->id)) }}%
							</div>
						</div>
					</td>
					<td class="whitespace-nowrap px-6 py-4">{{ $download->updated_at }}</td>
					<td class="whitespace-nowrap px-6 py-4">
						<div class="grid grid-cols-3 gap-2">
            
							<a href="/downloads/{{ $download->id }}" wire:navigate>
								<svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer">
									<path fill-rule="evenodd" clip-rule="evenodd" d="M22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12ZM12 17.75C12.4142 17.75 12.75 17.4142 12.75 17V11C12.75 10.5858 12.4142 10.25 12 10.25C11.5858 10.25 11.25 10.5858 11.25 11V17C11.25 17.4142 11.5858 17.75 12 17.75ZM12 7C12.5523 7 13 7.44772 13 8C13 8.55228 12.5523 9 12 9C11.4477 9 11 8.55228 11 8C11 7.44772 11.4477 7 12 7Z"/>
								</svg>
							</a>

							<button type="button" wire:click="pauseDownload({{ $download->id }})" title="{{ $download->paused ? __('Continue Download') : __('Pause Download') }}"> 
								@if($download->paused)
									<svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer">
										<path d="M21.4086 9.35258C23.5305 10.5065 23.5305 13.4935 21.4086 14.6474L8.59662 21.6145C6.53435 22.736 4 21.2763 4 18.9671L4 5.0329C4 2.72368 6.53435 1.26402 8.59661 2.38548L21.4086 9.35258Z"/>
									</svg>
								@else
									<svg width="24px" height="24px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer">
										<path fill-rule="evenodd" clip-rule="evenodd" d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22ZM8.07612 8.61732C8 8.80109 8 9.03406 8 9.5V14.5C8 14.9659 8 15.1989 8.07612 15.3827C8.17761 15.6277 8.37229 15.8224 8.61732 15.9239C8.80109 16 9.03406 16 9.5 16C9.96594 16 10.1989 16 10.3827 15.9239C10.6277 15.8224 10.8224 15.6277 10.9239 15.3827C11 15.1989 11 14.9659 11 14.5V9.5C11 9.03406 11 8.80109 10.9239 8.61732C10.8224 8.37229 10.6277 8.17761 10.3827 8.07612C10.1989 8 9.96594 8 9.5 8C9.03406 8 8.80109 8 8.61732 8.07612C8.37229 8.17761 8.17761 8.37229 8.07612 8.61732ZM13.0761 8.61732C13 8.80109 13 9.03406 13 9.5V14.5C13 14.9659 13 15.1989 13.0761 15.3827C13.1776 15.6277 13.3723 15.8224 13.6173 15.9239C13.8011 16 14.0341 16 14.5 16C14.9659 16 15.1989 16 15.3827 15.9239C15.6277 15.8224 15.8224 15.6277 15.9239 15.3827C16 15.1989 16 14.9659 16 14.5V9.5C16 9.03406 16 8.80109 15.9239 8.61732C15.8224 8.37229 15.6277 8.17761 15.3827 8.07612C15.1989 8 14.9659 8 14.5 8C14.0341 8 13.8011 8 13.6173 8.07612C13.3723 8.17761 13.1776 8.37229 13.0761 8.61732Z"/>
									</svg>
								@endif
							</button>

							@if($deleteModal == $download->id)
							<x-modal name="{{ __('Are you sure you want to delete this download?') }}" show="true" max-width="md">
								<div class="flex items-center justify-center">
									<div class=" rounded-lg overflow-hidden shadow-xl transform transition-all sm:max-w-lg sm:w-full" role="dialog" aria-modal="true" aria-labelledby="modal-headline">
										<div class="p-6">
											<div class="text-lg">
												{{ __('Are you sure you want to delete this download?') }}<br/>
											</div>

											{{ __($download->src_type) }}: {{ $download->name }}

											<div class="mt-4 flex justify-end">
												<button wire:click="deleteDownload({{ $download->id }})" class="btn btn-red mr-2">{{ __('Delete') }}</button>
												<button wire:click="closeDeleteModal" class="btn btn-gray">{{ __('Cancel') }}</button>
											</div>
										</div>
									</div>
								</div>
							</x-modal>
							@endif

							<button type="button" wire:click="openDeleteModal({{ $download->id }})" title="{{ __('Delete Download') }}" class="fill-gray-500 dark:fill-gray-400 hover:fill-gray-700 dark:hover:fill-gray-300 cursor-pointer"> 
								<svg width="24px" height="24px" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg">
									<path d="M512 64a448 448 0 1 1 0 896 448 448 0 0 1 0-896zM288 512a38.4 38.4 0 0 0 38.4 38.4h371.2a38.4 38.4 0 0 0 0-76.8H326.4A38.4 38.4 0 0 0 288 512z"/>
								</svg>
							</button>
						</div>

					</td>
				</tr>
			@endforeach

          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>