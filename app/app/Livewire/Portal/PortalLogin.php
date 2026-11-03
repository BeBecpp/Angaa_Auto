<?php

namespace App\Livewire\Portal;

use App\Actions\Auth\OtpService;
use App\Support\DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class PortalLogin extends Component
{
    public string $phone = '';

    public string $code = '';

    public bool $codeSent = false;

    public ?string $notice = null;

    public function sendCode(OtpService $otp): void
    {
        $this->resetErrorBag();
        $this->validate(['phone' => ['required', 'string', 'max:20']], ['phone.required' => 'Утасны дугаараа оруулна уу.']);

        try {
            $otp->request($this->phone, session()->getId(), (string) request()->ip());
            $this->codeSent = true;
            $this->notice = 'Код илгээлээ. 10 минутын дотор оруулна уу.';
        } catch (DomainException $exception) {
            $this->addError('phone', $exception->getMessage());
        }
    }

    public function verify(OtpService $otp): mixed
    {
        $this->resetErrorBag();
        $this->validate(['code' => ['required', 'digits:6']], ['code.required' => '6 оронтой кодоо оруулна уу.', 'code.digits' => '6 оронтой кодоо оруулна уу.']);

        try {
            $user = $otp->verify($this->phone, $this->code, session()->getId());
        } catch (DomainException $exception) {
            $this->addError('code', $exception->getMessage());

            return null;
        }

        Auth::login($user);
        session()->regenerate();

        return $this->redirectIntended(route('portal.home'));
    }

    public function render(OtpService $otp): View
    {
        return view('livewire.portal.portal-login', [
            'available' => $otp->portalAvailable(),
            'localCode' => $this->codeSent ? $otp->localPreview(session()->getId()) : null,
        ]);
    }
}
