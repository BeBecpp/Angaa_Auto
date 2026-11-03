<?php

namespace App\Http\Controllers;

use App\Enums\EstimateState;
use App\Enums\PlanStatus;
use App\Models\Invoice;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffPrintController extends Controller
{
    public function workOrder(Request $request, WorkOrder $workOrder): View
    {
        $user = $request->user();
        abort_unless($user->isOfficeStaff() || $workOrder->assigned_mechanic_id === $user->id, 403);

        $invoice = $workOrder->currentInvoice();

        return view('print.work-order', [
            'order' => $workOrder->load(['vehicle', 'repairItems']),
            'findings' => $workOrder->inspectionItems()->whereNotNull('published_at')->orderBy('sort_order')->get(),
            'revisions' => $workOrder->estimateRevisions()->where('state', '!=', EstimateState::Draft)->with(['lines', 'decision'])->get(),
            'invoice' => $invoice?->status->value === 'issued' ? $invoice : null,
            'plans' => $workOrder->maintenancePlans()->whereIn('status', [PlanStatus::Active, PlanStatus::Satisfied])->get(),
            'audience' => 'staff',
        ]);
    }

    public function invoice(Request $request, Invoice $invoice): View
    {
        abort_unless($request->user()->isOfficeStaff(), 403);

        return view('print.invoice', ['invoice' => $invoice->load(['lines', 'payments', 'workOrder'])]);
    }
}
