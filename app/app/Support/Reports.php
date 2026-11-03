<?php

namespace App\Support;

use App\Enums\AppointmentStatus;
use App\Enums\EstimateDecisionType;
use App\Enums\InvoiceStatus;
use App\Enums\RequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Appointment;
use App\Models\EstimateDecision;
use App\Models\Invoice;
use App\Models\NotificationOutbox;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;

/**
 * Owner reporting for a business-local date range [from, to] inclusive.
 */
class Reports
{
    /**
     * @return array<string, mixed>
     */
    public static function build(string $fromDate, string $toDate): array
    {
        $from = BusinessTime::fromLocal($fromDate.' 00:00');
        $to = BusinessTime::fromLocal($toDate.' 00:00')->addDay();

        $requests = ServiceRequest::query()->whereBetween('created_at', [$from, $to])->where('status', '!=', RequestStatus::Spam);
        $requestCount = (clone $requests)->count();
        $converted = (clone $requests)->whereNotNull('converted_at')->count();

        $responseHours = (clone $requests)->whereNotNull('first_responded_at')->get()
            ->map(fn (ServiceRequest $request): float => round($request->created_at->diffInMinutes($request->first_responded_at) / 60, 2))
            ->sort()->values();

        $appointments = Appointment::query()->whereBetween('planned_start', [$from, $to]);
        $arrived = (clone $appointments)->whereIn('status', [AppointmentStatus::Arrived, AppointmentStatus::Fulfilled])->count();
        $noShow = (clone $appointments)->where('status', AppointmentStatus::NoShow)->count();

        $completed = WorkOrder::query()->whereBetween('delivered_at', [$from, $to])->where('status', WorkOrderStatus::Delivered)->count();

        $decisions = EstimateDecision::query()->whereBetween('decided_at', [$from, $to]);
        $approved = (clone $decisions)->where('decision', EstimateDecisionType::Approved)->count();
        $declined = (clone $decisions)->where('decision', EstimateDecisionType::Declined)->count();

        $repeatCustomers = WorkOrder::query()->whereBetween('received_at', [$from, $to])
            ->get(['customer_id'])
            ->filter(fn (WorkOrder $order): bool => WorkOrder::query()->where('customer_id', $order->customer_id)->where('received_at', '<', $from)->exists())
            ->unique('customer_id')->count();

        $billed = (int) Invoice::query()->where('status', InvoiceStatus::Issued)->whereBetween('issued_at', [$from, $to])->sum('grand_total_mnt');
        $received = (int) Payment::query()->where('status', 'settled')->whereBetween('received_at', [$from, $to])->sum('amount_mnt')
            - (int) PaymentReversal::query()->whereBetween('occurred_at', [$from, $to])->sum('amount_mnt');
        $outstanding = Invoice::query()->where('status', InvoiceStatus::Issued)->get()->sum(fn (Invoice $invoice): int => $invoice->balanceMnt());

        return [
            'from' => $fromDate,
            'to' => $toDate,
            'requests' => $requestCount,
            'converted' => $converted,
            'arrived' => $arrived,
            'completed' => $completed,
            'response_median_h' => self::percentile($responseHours->all(), 50),
            'response_p90_h' => self::percentile($responseHours->all(), 90),
            'no_show_rate' => ($arrived + $noShow) > 0 ? round($noShow / ($arrived + $noShow) * 100, 1) : null,
            'approval_rate' => ($approved + $declined) > 0 ? round($approved / ($approved + $declined) * 100, 1) : null,
            'approved' => $approved,
            'declined' => $declined,
            'repeat_customers' => $repeatCustomers,
            'billed' => $billed,
            'received' => $received,
            'outstanding' => $outstanding,
            'rebooked_from_reminder' => NotificationOutbox::query()->where('skip_reason', 'rebooked')->whereBetween('updated_at', [$from, $to])->distinct('customer_id')->count('customer_id'),
            'generated_at' => CarbonImmutable::now()->toIso8601String(),
        ];
    }

    /**
     * @param  list<float>  $values  sorted ascending
     */
    public static function percentile(array $values, int $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        $index = (int) ceil($percentile / 100 * count($values)) - 1;

        return $values[max(0, min($index, count($values) - 1))];
    }

    /**
     * Neutralize spreadsheet formula injection in exported cells.
     */
    public static function csvSafe(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
