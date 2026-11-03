<?php

namespace App\Livewire\Portal;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Audit;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Customer asks staff to reschedule or cancel. It never changes reservations by itself.
 */
class AppointmentChange extends Component
{
    #[Locked]
    public int $appointmentId;

    public bool $open = false;

    public string $kind = 'reschedule';

    public string $reason = '';

    public function mount(Appointment $appointment): void
    {
        $this->authorizeAppointment($appointment);
        $this->appointmentId = $appointment->id;
    }

    public function submit(): void
    {
        $this->validate([
            'kind' => ['required', 'in:reschedule,cancel'],
            'reason' => ['required', 'string', 'min:3', 'max:300'],
        ], ['reason.required' => 'Шалтгаан эсвэл хүссэн цагаа бичнэ үү.', 'reason.min' => 'Шалтгаан эсвэл хүссэн цагаа бичнэ үү.']);

        $appointment = Appointment::query()->findOrFail($this->appointmentId);
        $this->authorizeAppointment($appointment);

        if ($appointment->status !== AppointmentStatus::Confirmed) {
            $this->addError('reason', 'Энэ цагийг өөрчлөх боломжгүй болсон байна.');

            return;
        }

        $label = $this->kind === 'cancel' ? 'Цуцлах хүсэлт' : 'Цаг өөрчлөх хүсэлт';
        $appointment->forceFill([
            'customer_change_request' => $label.': '.trim($this->reason),
            'customer_change_requested_at' => now(),
        ])->save();

        Audit::record('appointment.customer_change_requested', $appointment, ['kind' => $this->kind]);
        $this->open = false;
        $this->reason = '';
    }

    private function authorizeAppointment(Appointment $appointment): void
    {
        $customerId = auth()->user()?->customer?->id;
        abort_unless($customerId !== null && $appointment->customer_id === $customerId, 404);
    }

    public function render(): View
    {
        return view('livewire.portal.appointment-change', ['appointment' => Appointment::query()->findOrFail($this->appointmentId)]);
    }
}
