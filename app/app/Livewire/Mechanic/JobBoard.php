<?php

namespace App\Livewire\Mechanic;

use App\Actions\Scheduling\CheckInVehicle;
use App\Enums\AppointmentStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Today's arrivals and open jobs for the signed-in mechanic (office staff see everyone's).
 */
class JobBoard extends Component
{
    public ?int $checkingInId = null;

    public string $complaint = '';

    public string $intakeKm = '';

    public string $fuelLevel = '';

    public string $belongings = '';

    public string $damage = '';

    public string $kmCorrection = '';

    public function startCheckIn(int $appointmentId): void
    {
        $appointment = $this->appointmentsQuery()->findOrFail($appointmentId);
        $this->checkingInId = $appointment->id;
        $this->complaint = (string) ($appointment->serviceRequest?->symptoms ?? $appointment->service?->name_mn ?? '');
        $this->resetErrorBag();
    }

    public function checkIn(CheckInVehicle $action): mixed
    {
        $this->validate([
            'complaint' => ['required', 'string', 'max:2000'],
            'intakeKm' => ['required', 'integer', 'min:0', 'max:3000000'],
            'fuelLevel' => ['nullable', 'string', 'max:16'],
        ], [
            'complaint.required' => 'Гомдлыг бичнэ үү.',
            'intakeKm.required' => 'Гүйлтийн км оруулна уу.',
            'intakeKm.integer' => 'Км зөвхөн тоо.',
        ]);

        $appointment = $this->appointmentsQuery()->findOrFail($this->checkingInId);

        try {
            $order = $action->fromAppointment($appointment, [
                'complaint' => $this->complaint,
                'intake_km' => (int) $this->intakeKm,
                'fuel_level' => $this->fuelLevel ?: null,
                'belongings_note' => $this->belongings ?: null,
                'visible_damage_note' => $this->damage ?: null,
                'km_correction_note' => $this->kmCorrection ?: null,
            ], auth()->user());
        } catch (DomainException $exception) {
            $this->addError('checkin', $exception->getMessage());

            return null;
        }

        return $this->redirectRoute('mechanic.job', $order);
    }

    /**
     * @return Builder<Appointment>
     */
    private function appointmentsQuery()
    {
        $user = auth()->user();

        return Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->when(! $user->isOfficeStaff(), fn ($query) => $query->where('mechanic_user_id', $user->id));
    }

    public function render(): View
    {
        $user = auth()->user();
        $today = BusinessTime::today();

        return view('livewire.mechanic.job-board', [
            'arrivals' => $this->appointmentsQuery()
                ->whereBetween('planned_start', [$today->utc(), $today->addDay()->utc()])
                ->with(['vehicle', 'service', 'customer'])
                ->orderBy('planned_start')
                ->get(),
            'orders' => WorkOrder::query()
                ->whereNotIn('status', [WorkOrderStatus::Delivered, WorkOrderStatus::Cancelled])
                ->when(! $user->isOfficeStaff(), fn ($query) => $query->where('assigned_mechanic_id', $user->id))
                ->orderByRaw("case status when 'in_progress' then 0 when 'approved' then 1 when 'diagnosing' then 2 when 'received' then 3 else 4 end")
                ->orderBy('received_at')
                ->get(),
        ]);
    }
}
