<?php

use App\Models\Setting;
use App\Providers\RouteServiceProvider;
use App\Services\DebridServiceFactory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Validate; 
use Livewire\Volt\Component;

new class extends Component
{
    #[Validate('required')] 
    public $agent;

    #[Validate('required')] 
    public $token;

    public $accountStatus = [];

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->token = Setting::get('account_0_token');
        $this->getAccountStatus();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateAccounts(): void
    {
        Setting::updateOrCreate(['key' => 'account_0_token'], ['value' => $this->token]);
        Cache::delete('account_0_accountStatus');
        $this->getAccountStatus();
        $this->dispatch('accounts-updated');
    }

    private function getAccountStatus() {
        if(!empty($this->token)) {
            $this->accountStatus = Cache::remember('account_0_accountStatus', now()->addSeconds(15), function () {
                $alldebrid = DebridServiceFactory::createDebridService('alldebrid');
                return $alldebrid->getUserStatus();
            });
        }
    }


}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Accounts') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update the Account Settings") }}
        </p>
    </header>


    <form wire:submit="updateAccounts" class="mt-6 space-y-6">
        <div>
            <x-input-label for="token" :value="__('AllDebrid Token')" />
            <x-text-input wire:model="token" id="token" name="token" type="text" class="mt-1 block w-full" required />
            <x-input-error class="mt-2" :messages="$errors->get('token')" />
        </div>

        @if(!empty($accountStatus))

            @if(!$accountStatus['status'])

                <div class="flex items-center bg-red-500 text-white text-sm font-bold px-4 py-3" role="alert">
                    <p>{{ $accountStatus['message'] }}</p>
                </div>

            @else

                <div class="flex items-center bg-blue-500 text-white text-sm font-bold px-4 py-3" role="alert">
                    <p>{{ $accountStatus['message'] }}</p>
                </div>

            @endif
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            <x-action-message class="me-3" on="accounts-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
