<?php

namespace App\Http\Controllers;

use App\Actions\Maintenance\EvaluateDuePlans;
use App\Enums\AppointmentStatus;
use App\Enums\EstimateState;
use App\Enums\PlanStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Every query is scoped to the signed-in customer's own identity. Work-order history is restricted by
 * work_orders.customer_id; vehicle access alone never reveals another customer's past jobs.
 */
class PortalController extends Controller
{
    public function home(Request $request): View
    {
        $customer = $this->customer($request);

        if ($customer === null) {
            return view('portal.unlinked', ['phone' => Settings::contactPhone()]);
        }

        $orders = $customer->workOrders()->with('vehicle')->latest('received_at')->get();

        return view('portal.home', [
            'customer' => $customer,
            'vehicles' => $customer->currentVehicles()->get(),
            'upcoming' => $customer->appointments()->whereIn('status', [AppointmentStatus::Confirmed])->where('planned_start', '>=', now())->orderBy('planned_start')->first(),
            'activeOrders' => $orders->filter(fn (WorkOrder $order): bool => $order->status->isOpen()),
            'pendingEstimates' => $orders->filter(fn (WorkOrder $order): bool => $order->estimateRevisions()->where('state', EstimateState::Sent)->exists()),
            'nextPlan' => $customer->maintenancePlans()->where('status', PlanStatus::Active)->orderByRaw('due_date is null, due_date')->with('vehicle')->first(),
        ]);
    }

    public function vehicle(Request $request, Vehicle $vehicle): View
    {
        $customer = $this->requireCustomer($request);

        $orders = $customer->workOrders()->where('vehicle_id', $vehicle->id)->latest('received_at')->get();
        $hasCurrentAccess = $customer->currentVehicles()->whereKey($vehicle->id)->exists();

        abort_unless($hasCurrentAccess || $orders->isNotEmpty(), 404);

        $plans = $hasCurrentAccess
            ? $vehicle->maintenancePlans()->where('customer_id', $customer->id)->whereIn('status', [PlanStatus::Active, PlanStatus::Paused])->get()
            : collect();

        return view('portal.vehicle', [
            'vehicle' => $vehicle,
            'orders' => $orders,
            'plans' => $plans,
            'reading' => $hasCurrentAccess ? $vehicle->latestVerifiedReading() : null,
            'evaluator' => app(EvaluateDuePlans::class),
            'now' => CarbonImmutable::now(),
        ]);
    }

    public function job(Request $request, WorkOrder $workOrder): View
    {
        $this->authorizeOrder($request, $workOrder);

        return view('portal.job', $this->jobData($workOrder));
    }

    public function jobPrint(Request $request, WorkOrder $workOrder): View
    {
        $this->authorizeOrder($request, $workOrder);

        return view('print.work-order', $this->jobData($workOrder) + ['audience' => 'customer']);
    }

    public function appointments(Request $request): View
    {
        $customer = $this->requireCustomer($request);

        return view('portal.appointments', [
            'appointments' => $customer->appointments()->with(['vehicle', 'service'])->latest('planned_start')->limit(30)->get(),
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * @return array<string, mixed>
     */
    private function jobData(WorkOrder $workOrder): array
    {
        $invoice = $workOrder->currentInvoice();

        return [
            'order' => $workOrder->load(['vehicle', 'statusEvents', 'repairItems']),
            'findings' => $workOrder->inspectionItems()->whereNotNull('published_at')->orderBy('sort_order')->get(),
            'photos' => $workOrder->media()->where('customer_visible', true)->orderBy('created_at')->get(),
            'revisions' => $workOrder->estimateRevisions()->where('state', '!=', EstimateState::Draft)->with(['lines', 'decision'])->orderByDesc('revision_number')->get(),
            'invoice' => $invoice?->status->value === 'issued' ? $invoice : null,
            'plans' => $workOrder->maintenancePlans()->whereIn('status', [PlanStatus::Active, PlanStatus::Satisfied])->get(),
            'timeline' => $workOrder->statusEvents()->orderBy('occurred_at')->get()
                ->reject(fn ($event): bool => $event->to_status === WorkOrderStatus::Received && $event->from_status !== null),
        ];
    }

    private function authorizeOrder(Request $request, WorkOrder $workOrder): void
    {
        $customer = $this->requireCustomer($request);

        abort_unless($workOrder->customer_id === $customer->id, 404);
    }

    private function customer(Request $request): ?Customer
    {
        return Customer::query()->where('user_id', $request->user()->id)->whereNull('archived_at')->first();
    }

    private function requireCustomer(Request $request): Customer
    {
        $customer = $this->customer($request);
        abort_if($customer === null, 404);

        return $customer;
    }
}
