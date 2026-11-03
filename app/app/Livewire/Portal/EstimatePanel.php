<?php

namespace App\Livewire\Portal;

use App\Actions\Workshop\RecordEstimateDecision;
use App\Enums\DecisionChannel;
use App\Enums\EstimateDecisionType;
use App\Enums\EstimateState;
use App\Models\EstimateRevision;
use App\Support\DomainException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Approve/decline exactly the displayed revision. The content hash captured at render time is sent
 * back, so an old tab can never approve a revision that changed or was superseded.
 */
class EstimatePanel extends Component
{
    #[Locked]
    public int $revisionId;

    #[Locked]
    public string $displayedHash = '';

    public ?string $confirming = null;

    public string $declineReason = '';

    public ?string $result = null;

    public function mount(EstimateRevision $revision): void
    {
        $this->authorizeRevision($revision);
        $this->revisionId = $revision->id;
        $this->displayedHash = (string) $revision->content_hash;
    }

    public function ask(string $decision): void
    {
        $this->confirming = in_array($decision, ['approved', 'declined'], true) ? $decision : null;
    }

    public function decide(RecordEstimateDecision $action): void
    {
        $revision = EstimateRevision::query()->findOrFail($this->revisionId);
        $this->authorizeRevision($revision);

        if ($this->confirming === null) {
            return;
        }

        try {
            $action->handle(
                $revision,
                EstimateDecisionType::from($this->confirming),
                DecisionChannel::Portal,
                auth()->user(),
                $this->displayedHash,
                reason: $this->confirming === 'declined' ? ($this->declineReason ?: null) : null,
                ip: request()->ip(),
            );
            $this->result = $this->confirming === 'approved'
                ? 'Баярлалаа. Та хувилбар #'.$revision->revision_number.'-ийг зөвшөөрлөө. Засвар энэ хүрээнд хийгдэнэ.'
                : 'Таны татгалзсан хариуг бүртгэлээ. Ажилтан тантай холбогдоно.';
            $this->confirming = null;
        } catch (DomainException $exception) {
            $this->addError('decision', $exception->getMessage());
            $this->confirming = null;
        }
    }

    private function authorizeRevision(EstimateRevision $revision): void
    {
        $customerId = auth()->user()?->customer?->id;
        abort_unless($customerId !== null && $revision->workOrder->customer_id === $customerId, 404);
    }

    public function render(): View
    {
        $revision = EstimateRevision::query()->with(['lines', 'decision'])->findOrFail($this->revisionId);

        return view('livewire.portal.estimate-panel', [
            'revision' => $revision,
            'actionable' => $revision->state === EstimateState::Sent && $this->result === null,
        ]);
    }
}
