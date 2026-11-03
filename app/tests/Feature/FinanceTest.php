<?php

namespace Tests\Feature;

use App\Actions\Finance\InvoiceService;
use App\Actions\Finance\PaymentService;
use App\Actions\Workshop\RecordEstimateDecision;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentState;
use App\Enums\WorkOrderStatus;
use App\Models\Invoice;
use App\Models\WorkOrder;
use App\Support\BusinessTime;
use App\Support\DomainException;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

class FinanceTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();
    }

    private function approvedOrder(): WorkOrder
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        [$order, $revision] = $this->orderWithSentEstimate($customer, $vehicle);
        app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::InPerson, $this->advisor, $revision->content_hash, 'Биечлэн зөвшөөрсөн');

        return $order->refresh();
    }

    private function issuedInvoice(): Invoice
    {
        $service = app(InvoiceService::class);

        return $service->issue($service->createDraft($this->approvedOrder(), $this->advisor), $this->advisor);
    }

    public function test_fractional_quantities_round_once_per_line_in_integer_mnt(): void
    {
        $this->assertSame(1500, Money::quantityToMilli('1.5'));
        $this->assertSame(1250, Money::quantityToMilli('1,25'));
        $this->assertSame(21, Money::lineTotal(1333, 16));
        $this->assertSame(63000, Money::lineTotal(4500, 14000));
        $this->assertSame(10000, Money::lineTotal(1000, 12000, 2000));
        $this->assertSame(0, Money::lineTotal(1000, 1000, 5000));
    }

    public function test_issued_invoice_is_frozen_and_numbered(): void
    {
        $invoice = $this->issuedInvoice();

        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertMatchesRegularExpression('/^INV-\d{4}-\d{4}$/', $invoice->invoice_number);
        $this->assertSame(103000, $invoice->grand_total_mnt);
        $this->assertSame(103000, $invoice->issue_snapshot['grand_total_mnt']);

        $this->expectException(DomainException::class);
        app(InvoiceService::class)->saveDraftLines($invoice, [['kind' => 'labor', 'description' => 'x', 'quantity' => '1', 'unit_price_mnt' => 1]], $this->advisor);
    }

    public function test_partial_then_final_payment_and_idempotent_duplicate(): void
    {
        $invoice = $this->issuedInvoice();
        $payments = app(PaymentService::class);

        $payments->record($invoice, 40000, PaymentMethod::Cash, $this->advisor, 'key-1');
        $payments->record($invoice, 40000, PaymentMethod::Cash, $this->advisor, 'key-1');
        $invoice->refresh();

        $this->assertSame(40000, $invoice->paidNetMnt());
        $this->assertSame(63000, $invoice->balanceMnt());
        $this->assertSame(PaymentState::PartPaid, $invoice->paymentState());

        try {
            $payments->record($invoice, 70000, PaymentMethod::Bank, $this->advisor, 'key-2');
            $this->fail('Overpayment must be rejected');
        } catch (DomainException) {
        }

        $payments->record($invoice, 63000, PaymentMethod::Bank, $this->advisor, 'key-3', 'TX-1');

        try {
            $payments->record($invoice, 1, PaymentMethod::Bank, $this->advisor, 'key-4', 'TX-1');
            $this->fail('Duplicate reference must be rejected');
        } catch (DomainException) {
        }

        $this->assertSame(PaymentState::Paid, $invoice->refresh()->paymentState());
        $this->assertSame(0, $invoice->balanceMnt());
    }

    public function test_reversal_is_owner_only_bounded_and_audited(): void
    {
        $invoice = $this->issuedInvoice();
        $payment = app(PaymentService::class)->record($invoice, 50000, PaymentMethod::Cash, $this->advisor, 'p1');
        $payments = app(PaymentService::class);

        try {
            $payments->reverse($payment, 1000, 'алдаа', $this->advisor, 'r0');
            $this->fail('Advisor cannot reverse');
        } catch (DomainException) {
        }

        $payments->reverse($payment, 20000, 'Буруу дүн', $this->owner, 'r1');
        $payments->reverse($payment, 20000, 'Буруу дүн', $this->owner, 'r1');

        try {
            $payments->reverse($payment, 40000, 'хэт их', $this->owner, 'r2');
            $this->fail('Total reversed cannot exceed the payment');
        } catch (DomainException) {
        }

        $this->assertSame(30000, $invoice->refresh()->paidNetMnt());
        $this->assertSame(73000, $invoice->balanceMnt());
        $this->assertDatabaseHas('audit_events', ['action' => 'payment.reversed']);
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_void_requires_owner_and_no_net_payments(): void
    {
        $invoice = $this->issuedInvoice();
        app(PaymentService::class)->record($invoice, 1000, PaymentMethod::Cash, $this->advisor, 'p');

        $this->expectException(DomainException::class);
        app(InvoiceService::class)->void($invoice, 'алдаа', $this->owner);
    }

    public function test_delivery_with_balance_needs_owner_credit_reason_and_stays_unpaid(): void
    {
        $order = $this->approvedOrder();
        $workshop = app(WorkshopService::class);
        app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::InProgress, $this->mechanic);
        $workshop->recordRepair($order->refresh(), ['description' => 'Дэвсгэр', 'estimate_line_id' => $order->currentApprovedRevision->lines->first()->id], $this->mechanic);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::QualityCheck, $this->mechanic);
        $workshop->completeQualityCheck($order->refresh(), [['item' => 'Туршилт', 'result' => 'pass']], $this->advisor);

        $invoices = app(InvoiceService::class);
        $invoice = $invoices->issue($invoices->createDraft($order->refresh(), $this->advisor), $this->advisor);

        try {
            $workshop->deliver($order->refresh(), $this->advisor);
            $this->fail('Advisor cannot deliver with a balance');
        } catch (DomainException) {
        }

        $workshop->deliver($order->refresh(), $this->owner, 'Маргааш шилжүүлнэ');

        $this->assertSame(WorkOrderStatus::Delivered, $order->refresh()->status);
        $this->assertSame(PaymentState::Unpaid, $invoice->refresh()->paymentState());
        $this->assertSame('Маргааш шилжүүлнэ', $order->unpaid_delivery_reason);
    }
}
