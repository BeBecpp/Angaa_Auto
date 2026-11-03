<?php

namespace App\Actions\Workshop;

use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\EstimateState;
use App\Enums\WorkOrderStatus;
use App\Models\EstimateDecision;
use App\Models\EstimateRevision;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Audit;
use App\Support\BusinessTime;
use App\Support\DomainException;
use App\Support\Serialized;

/**
 * Records a customer's decision on exactly one displayed revision. Rejects stale tabs (superseded or
 * changed content), expired offers and duplicates. Phone/in-person decisions are staff-recorded
 * attestations, never labelled as the customer's electronic signature.
 */
class RecordEstimateDecision
{
    public function __construct(private TransitionWorkOrder $transition) {}

    public function handle(
        EstimateRevision $revision,
        EstimateDecisionType $decision,
        DecisionChannel $channel,
        User $actor,
        string $displayedContentHash,
        ?string $staffNote = null,
        ?string $reason = null,
        ?string $ip = null,
    ): EstimateDecision {
        return Serialized::run('work_order:'.$revision->work_order_id, function () use ($revision, $decision, $channel, $actor, $displayedContentHash, $staffNote, $reason, $ip): EstimateDecision {
            $order = WorkOrder::query()->whereKey($revision->work_order_id)->lockForUpdate()->firstOrFail();
            $fresh = EstimateRevision::query()->whereKey($revision->id)->lockForUpdate()->firstOrFail();

            $this->authorize($order, $channel, $actor, $staffNote);

            if ($fresh->decision()->exists()) {
                $existing = $fresh->decision;

                if ($existing->decision === $decision) {
                    return $existing;
                }

                throw new DomainException('Энэ хувилбарт аль хэдийн хариу өгсөн байна.');
            }

            if ($fresh->state !== EstimateState::Sent) {
                throw new DomainException('Энэ үнийн санал хүчингүй болсон (шинэ хувилбар гарсан эсвэл хаагдсан). Хуудсаа шинэчилнэ үү.');
            }

            if (! hash_equals((string) $fresh->content_hash, $displayedContentHash)) {
                throw new DomainException('Таны харж буй үнийн санал өөрчлөгдсөн байна. Хуудсаа шинэчилж дахин шалгана уу.');
            }

            $latestNumber = (int) $order->estimateRevisions()->max('revision_number');

            if ($fresh->revision_number !== $latestNumber) {
                throw new DomainException('Илүү шинэ хувилбар байна. Хуудсаа шинэчилнэ үү.');
            }

            if ($decision === EstimateDecisionType::Approved && $fresh->valid_until && BusinessTime::today()->toDateString() > $fresh->valid_until->toDateString()) {
                $fresh->forceFill(['state' => EstimateState::Expired])->save();

                throw new DomainException('Үнийн саналын хүчинтэй хугацаа дууссан. Шинэ санал авахаар холбогдоно уу.');
            }

            $record = EstimateDecision::query()->create([
                'estimate_revision_id' => $fresh->id,
                'decision' => $decision,
                'channel' => $channel,
                'actor_user_id' => $actor->id,
                'customer_id' => $order->customer_id,
                'decided_at' => now(),
                'amount_snapshot_mnt' => $fresh->grand_total_mnt,
                'content_hash' => $fresh->content_hash,
                'staff_attestation_note' => $channel === DecisionChannel::Portal ? null : $staffNote,
                'reason' => $reason,
                'ip_hash' => $ip ? hash('sha256', $ip.config('app.key')) : null,
            ]);

            $fresh->forceFill(['state' => $decision === EstimateDecisionType::Approved ? EstimateState::Approved : EstimateState::Declined])->save();

            if ($decision === EstimateDecisionType::Approved) {
                $order->forceFill(['current_approved_estimate_revision_id' => $fresh->id])->save();

                if ($order->status === WorkOrderStatus::AwaitingApproval) {
                    $this->transition->system($order, WorkOrderStatus::Approved, $actor, 'Үнийн санал #'.$fresh->revision_number.' зөвшөөрсөн ('.$channel->getLabel().')');
                }
            } else {
                $declined = trim(($order->declined_recommendations ? $order->declined_recommendations."\n" : '').'#'.$fresh->revision_number.': '.($reason ?: 'татгалзсан'));
                $order->forceFill(['declined_recommendations' => $declined])->save();

                if ($order->status === WorkOrderStatus::AwaitingApproval && $order->current_approved_estimate_revision_id !== null) {
                    $this->transition->system($order, WorkOrderStatus::Approved, $actor, 'Нэмэлт ажлаас татгалзсан; өмнө зөвшөөрсөн хүрээгээр үргэлжилнэ');
                }
            }

            Audit::record('estimate.decision', $fresh, [
                'decision' => $decision->value,
                'channel' => $channel->value,
                'amount' => $fresh->grand_total_mnt,
            ], $reason, $actor->id);

            return $record;
        });
    }

    private function authorize(WorkOrder $order, DecisionChannel $channel, User $actor, ?string $staffNote): void
    {
        if ($channel === DecisionChannel::Portal) {
            if (! $actor->isCustomer() || $actor->customer?->id !== $order->customer_id) {
                throw new DomainException('Энэ үнийн саналд хариу өгөх эрхгүй байна.');
            }

            return;
        }

        if ($channel === DecisionChannel::OwnerOverride) {
            throw new DomainException('Эзний онцгой зөвшөөрлийг «Зөвшөөрөлгүй ажил эхлүүлэх» үйлдлээр тусад нь бүртгэнэ.');
        }

        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Утсаар/биечлэн өгсөн хариуг зөвхөн эзэн эсвэл зөвлөх бүртгэнэ.');
        }

        if (trim((string) $staffNote) === '') {
            throw new DomainException('Хэрэглэгчтэй хэзээ, хэрхэн ярьсныг тэмдэглэнэ үү.');
        }
    }
}
