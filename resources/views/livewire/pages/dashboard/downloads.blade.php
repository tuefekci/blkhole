<?php

use Livewire\Volt\Component;
use App\Models\Download;


new class extends Component
{

	public $downloads;

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
					<td class="whitespace-nowrap px-6 py-4"></td>
				</tr>
			@endforeach

          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>