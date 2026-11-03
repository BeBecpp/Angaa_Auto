<?php

namespace App\Livewire\Portal;

use App\Actions\Maintenance\ConsentService;
use App\Enums\ConsentPurpose;
use App\Models\Customer;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\View\View;
use Livewire\Component;

class PortalPreferences extends Component
{
    public bool $serviceReminders = false;

    public bool $marketing = false;

    public ?string $saved = null;

    public bool $exportRequested = false;

    public function mount(ConsentService $consents): void
    {
        $customer = $this->customer();

        if ($customer) {
            $this->serviceReminders = $consents->has($customer, ConsentPurpose::ServiceReminder);
            $this->marketing = $consents->has($customer, ConsentPurpose::Marketing);
        }
    }

    public function save(ConsentService $consents): void
    {
        $customer = $this->customer();
        abort_if($customer === null, 404);

        foreach ([ConsentPurpose::ServiceReminder->value => $this->serviceReminders, ConsentPurpose::Marketing->value => $this->marketing] as $purpose => $enabled) {
            $enabled
                ? $consents->grant($customer, ConsentPurpose::from($purpose), 'portal', auth()->id())
                : $consents->revoke($customer, ConsentPurpose::from($purpose), 'portal', auth()->id());
        }

        $this->saved = 'Тохиргоог хадгаллаа.';
    }

    public function requestExport(): void
    {
        $customer = $this->customer();
        abort_if($customer === null, 404);

        Audit::record('customer.data_export_requested', $customer);
        $this->exportRequested = true;
    }

    private function customer(): ?Customer
    {
        return Customer::query()->where('user_id', auth()->id())->first();
    }

    public function render(): View
    {
        return view('livewire.portal.portal-preferences', [
            'customer' => $this->customer(),
            'verifiedPhone' => Phone::display(auth()->user()->phone_normalized),
        ]);
    }
}
