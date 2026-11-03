<?php

namespace Tests\Feature;

use App\Actions\Workshop\EstimateService;
use App\Actions\Workshop\RecordEstimateDecision;
use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\EstimateState;
use App\Enums\WorkOrderStatus;
use App\Livewire\Portal\EstimatePanel;
use App\Models\EstimateDecision;
use App\Models\User;
use App\Support\BusinessTime;
use App\Support\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\BuildsWorkshop;
use Tests\TestCase;

class EstimateApprovalTest extends TestCase
{
    use BuildsWorkshop, RefreshDatabase;

    private User $customerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(BusinessTime::fromLocal('2026-11-04 08:00'));
        $this->setUpWorkshop();
        $this->customerUser = User::factory()->customer()->create();
    }

    public function test_estimate_totals_are_computed_server_side_and_frozen_when_sent(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [$order, $revision] = $this->orderWithSentEstimate($customer, $vehicle);

        $this->assertSame(103000, $revision->grand_total_mnt);
        $this->assertSame(WorkOrderStatus::AwaitingApproval, $order->status);
        $this->assertSame(EstimateState::Sent, $revision->state);

        $this->expectException(DomainException::class);
        app(EstimateService::class)->saveDraft($revision, [], [['kind' => 'labor', 'description' => 'x', 'quantity' => '1', 'unit_price_mnt' => 1]], $this->advisor);
    }

    public function test_customer_approves_the_exact_displayed_revision_in_the_portal(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [$order, $revision] = $this->orderWithSentEstimate($customer, $vehicle);

        $this->actingAs($this->customerUser);
        Livewire::test(EstimatePanel::class, ['revision' => $revision])
            ->call('ask', 'approved')
            ->call('decide')
            ->assertHasNoErrors()
            ->assertSee('зөвшөөрлөө');

        $decision = EstimateDecision::query()->sole();
        $this->assertSame(DecisionChannel::Portal, $decision->channel);
        $this->assertSame(103000, $decision->amount_snapshot_mnt);
        $this->assertSame($revision->content_hash, $decision->content_hash);
        $this->assertSame(WorkOrderStatus::Approved, $order->refresh()->status);
        $this->assertSame($revision->id, $order->current_approved_estimate_revision_id);
    }

    public function test_old_tab_cannot_approve_a_superseded_revision_and_double_click_records_once(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [$order, $revision1] = $this->orderWithSentEstimate($customer, $vehicle);

        $this->actingAs($this->customerUser);
        $oldTab = Livewire::test(EstimatePanel::class, ['revision' => $revision1]);

        $estimates = app(EstimateService::class);
        $draft = $estimates->createDraft($order->refresh(), $this->advisor);
        $estimates->saveDraft($draft, [], [['kind' => 'labor', 'description' => 'Илүү ажил', 'quantity' => '2', 'unit_price_mnt' => 50000]], $this->advisor);
        $revision2 = $estimates->publish($draft, $this->advisor);

        $this->assertSame(EstimateState::Superseded, $revision1->refresh()->state);
        $oldTab->call('ask', 'approved')->call('decide')->assertHasErrors('decision');
        $this->assertSame(0, EstimateDecision::query()->count());

        $decide = fn () => app(RecordEstimateDecision::class)->handle($revision2->refresh(), EstimateDecisionType::Approved, DecisionChannel::Portal, $this->customerUser, $revision2->content_hash);
        $decide();
        $decide();

        $this->assertSame(1, EstimateDecision::query()->count());
    }

    public function test_changed_content_hash_is_rejected(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [, $revision] = $this->orderWithSentEstimate($customer, $vehicle);

        $this->expectExceptionMessage('өөрчлөгдсөн');
        app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Portal, $this->customerUser, str_repeat('0', 64));
    }

    public function test_another_customer_cannot_decide(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [, $revision] = $this->orderWithSentEstimate($customer, $vehicle);
        $intruder = User::factory()->customer()->create();
        $this->customerWithVehicle($intruder);

        $this->expectException(DomainException::class);
        app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Portal, $intruder, $revision->content_hash);
    }

    public function test_unapproved_work_cannot_start_except_by_audited_owner_override(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        [$order] = $this->orderWithSentEstimate($customer, $vehicle);
        $workshop = app(WorkshopService::class);

        try {
            $workshop->recordRepair($order, ['description' => 'Дэвсгэр'], $this->mechanic);
            $this->fail('Repair before approval must be blocked');
        } catch (DomainException) {
        }

        try {
            $workshop->ownerOverride($order, 'Яаралтай', $this->advisor);
            $this->fail('Only the owner may override');
        } catch (DomainException) {
        }

        $workshop->ownerOverride($order->refresh(), 'Хэрэглэгч эмнэлэгт, аюулгүй байдлын улмаас', $this->owner);
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::InProgress, $this->mechanic);

        $this->assertSame(WorkOrderStatus::InProgress, $order->refresh()->status);
        $this->assertDatabaseHas('audit_events', ['action' => 'work_order.owner_override', 'subject_id' => $order->id]);
        $this->assertSame(0, EstimateDecision::query()->count());
    }

    public function test_additional_scope_needs_a_new_approval_and_decline_keeps_prior_scope(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [$order, $revision1] = $this->orderWithSentEstimate($customer, $vehicle);
        app(RecordEstimateDecision::class)->handle($revision1, EstimateDecisionType::Approved, DecisionChannel::Phone, $this->advisor, $revision1->content_hash, 'Утсаар 10:05-д зөвшөөрсөн');
        app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::InProgress, $this->mechanic);

        $estimates = app(EstimateService::class);
        $draft = $estimates->createDraft($order->refresh(), $this->advisor);
        $estimates->saveDraft($draft, [], [
            ['kind' => 'labor', 'description' => 'Дэвсгэр солих', 'quantity' => '1', 'unit_price_mnt' => 35000],
            ['kind' => 'part', 'description' => 'Дэвсгэр', 'quantity' => '1', 'unit_price_mnt' => 68000],
            ['kind' => 'part', 'description' => 'Диск', 'quantity' => '2', 'unit_price_mnt' => 90000],
        ], $this->advisor);
        $revision2 = $estimates->publish($draft, $this->advisor);

        $this->assertSame(WorkOrderStatus::AwaitingApproval, $order->refresh()->status);
        $this->assertSame($revision1->id, $order->current_approved_estimate_revision_id);

        try {
            app(TransitionWorkOrder::class)->handle($order->refresh(), WorkOrderStatus::QualityCheck, $this->mechanic);
            $this->fail('Cannot proceed while new scope is pending');
        } catch (DomainException) {
        }

        app(RecordEstimateDecision::class)->handle($revision2->refresh(), EstimateDecisionType::Declined, DecisionChannel::Portal, $this->customerUser, $revision2->content_hash, reason: 'Дараа хийлгэнэ');

        $order->refresh();
        $this->assertSame(WorkOrderStatus::Approved, $order->status);
        $this->assertSame($revision1->id, $order->current_approved_estimate_revision_id);
        $this->assertStringContainsString('Дараа хийлгэнэ', $order->declined_recommendations);
        $this->assertSame(EstimateState::Approved, $revision1->refresh()->state);
    }

    public function test_phone_approval_is_a_staff_attestation_not_a_customer_signature(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        [, $revision] = $this->orderWithSentEstimate($customer, $vehicle);

        try {
            app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Phone, $this->advisor, $revision->content_hash);
            $this->fail('Phone approval needs an attestation note');
        } catch (DomainException) {
        }

        try {
            app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Phone, $this->mechanic, $revision->content_hash, 'note');
            $this->fail('Mechanic cannot record approvals');
        } catch (DomainException) {
        }

        $decision = app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Phone, $this->advisor, $revision->content_hash, '14:10 утсаар, хэрэглэгч өөрөө');
        $this->assertSame($this->advisor->id, $decision->actor_user_id);
        $this->assertSame('14:10 утсаар, хэрэглэгч өөрөө', $decision->staff_attestation_note);
    }

    public function test_mechanic_cannot_edit_prices_publish_or_deliver(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $order = $this->checkedInOrder($customer, $vehicle);
        app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::Diagnosing, $this->mechanic);

        $this->expectException(DomainException::class);
        app(EstimateService::class)->createDraft($order->refresh(), $this->mechanic);
    }

    public function test_mechanic_cannot_make_arbitrary_status_jumps(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle();
        $order = $this->checkedInOrder($customer, $vehicle);

        $this->expectException(DomainException::class);
        app(TransitionWorkOrder::class)->handle($order, WorkOrderStatus::ReadyForPickup, $this->mechanic);
    }

    public function test_expired_offer_cannot_be_approved(): void
    {
        [$customer, $vehicle] = $this->customerWithVehicle($this->customerUser);
        [, $revision] = $this->orderWithSentEstimate($customer, $vehicle);
        $this->travelTo(BusinessTime::fromLocal('2026-12-01 10:00'));

        $this->expectExceptionMessage('хугацаа дууссан');
        app(RecordEstimateDecision::class)->handle($revision, EstimateDecisionType::Approved, DecisionChannel::Portal, $this->customerUser, $revision->content_hash);
    }
}
