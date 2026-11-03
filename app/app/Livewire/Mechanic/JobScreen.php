<?php

namespace App\Livewire\Mechanic;

use App\Actions\Workshop\TransitionWorkOrder;
use App\Actions\Workshop\WorkshopService;
use App\Enums\InspectionCondition;
use App\Enums\MediaCategory;
use App\Enums\WorkOrderStatus;
use App\Models\InspectionItem;
use App\Models\WorkOrder;
use App\Support\DomainException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Phone-first job screen: one primary action per state, inspection, photos, repair log, QC handoff.
 * No prices, costs or payment controls are shown here.
 */
class JobScreen extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $workOrderId;

    public string $itemLabel = '';

    public string $itemCondition = 'attention';

    public string $itemInternal = '';

    public string $itemCustomer = '';

    public ?int $editingItemId = null;

    public $photo = null;

    public string $photoCategory = 'before';

    public string $photoCaption = '';

    public string $photoItemId = '';

    public string $repairLineId = '';

    public string $repairWork = '';

    public string $waitReason = '';

    public ?string $flash = null;

    public function mount(WorkOrder $workOrder): void
    {
        $this->workOrderId = $workOrder->id;
        $this->order();
    }

    public function moveStatus(string $target, TransitionWorkOrder $action): void
    {
        $this->run(function () use ($target, $action): void {
            $status = WorkOrderStatus::from($target);
            $reason = $status === WorkOrderStatus::WaitingParts ? $this->waitReason : null;
            $action->handle($this->order(), $status, auth()->user(), $reason);
            $this->waitReason = '';
            $this->flash = 'Төлөв шинэчлэгдлээ: '.$status->getLabel();
        });
    }

    public function editItem(int $id): void
    {
        $item = $this->order()->inspectionItems()->findOrFail($id);
        $this->editingItemId = $item->id;
        $this->itemLabel = $item->label;
        $this->itemCondition = $item->condition->value;
        $this->itemInternal = (string) $item->finding_internal;
        $this->itemCustomer = (string) $item->finding_customer;
    }

    public function saveItem(WorkshopService $workshop): void
    {
        $this->validate([
            'itemLabel' => ['required', 'string', 'max:120'],
            'itemCondition' => ['required', Rule::enum(InspectionCondition::class)],
            'itemInternal' => ['nullable', 'string', 'max:2000'],
            'itemCustomer' => ['nullable', 'string', 'max:2000'],
        ], ['itemLabel.required' => 'Шалгасан хэсгийн нэрийг бичнэ үү.']);

        $this->run(function () use ($workshop): void {
            $item = $this->editingItemId ? InspectionItem::query()->where('work_order_id', $this->workOrderId)->findOrFail($this->editingItemId) : null;
            $workshop->saveInspectionItem($this->order(), [
                'label' => $this->itemLabel,
                'condition' => $this->itemCondition,
                'finding_internal' => $this->itemInternal ?: null,
                'finding_customer' => $this->itemCustomer ?: null,
            ], auth()->user(), $item);
            $this->reset(['itemLabel', 'itemInternal', 'itemCustomer', 'editingItemId']);
            $this->itemCondition = 'attention';
            $this->flash = 'Үзлэгийн мөр хадгалагдлаа (хэрэглэгчид зөвлөх нийтэлнэ).';
        });
    }

    public function uploadPhoto(WorkshopService $workshop): void
    {
        $this->validate([
            'photo' => ['required', 'file', 'max:8192', 'mimes:jpg,jpeg,png,webp'],
            'photoCategory' => ['required', Rule::enum(MediaCategory::class)],
            'photoCaption' => ['nullable', 'string', 'max:160'],
        ], ['photo.required' => 'Зураг сонгоно уу.', 'photo.mimes' => 'Зөвхөн JPEG, PNG, WebP.', 'photo.max' => '8 MB-аас ихгүй.']);

        $this->run(function () use ($workshop): void {
            $workshop->attachPhoto(
                $this->order(),
                $this->photo,
                MediaCategory::from($this->photoCategory),
                auth()->user(),
                $this->photoCaption ?: null,
                $this->photoItemId !== '' ? (int) $this->photoItemId : null,
            );
            $this->reset(['photo', 'photoCaption', 'photoItemId']);
            $this->flash = 'Зураг нэмэгдлээ.';
        });
    }

    public function recordRepair(WorkshopService $workshop): void
    {
        $this->validate([
            'repairLineId' => ['required', 'integer'],
            'repairWork' => ['nullable', 'string', 'max:2000'],
        ], ['repairLineId.required' => 'Зөвшөөрсөн ажлын мөрийг сонгоно уу.']);

        $this->run(function () use ($workshop): void {
            $order = $this->order();
            $line = $order->currentApprovedRevision?->lines()->findOrFail((int) $this->repairLineId);
            $workshop->recordRepair($order, [
                'description' => $line->description,
                'work_done' => $this->repairWork ?: null,
                'estimate_line_id' => $line->id,
                'quantity_milli' => $line->quantity_milli,
                'is_part' => $line->kind->value === 'part',
            ], auth()->user());
            $this->reset(['repairLineId', 'repairWork']);
            $this->flash = 'Хийсэн ажил бүртгэгдлээ.';
        });
    }

    private function run(callable $callback): void
    {
        $this->resetErrorBag('action');
        $this->flash = null;

        try {
            $callback();
        } catch (DomainException $exception) {
            $this->addError('action', $exception->getMessage());
        }
    }

    private function order(): WorkOrder
    {
        $order = WorkOrder::query()->findOrFail($this->workOrderId);
        $user = auth()->user();
        abort_unless($user->isOfficeStaff() || $order->assigned_mechanic_id === $user->id, 404);

        return $order;
    }

    public function render(): View
    {
        $order = $this->order()->load(['inspectionItems', 'repairItems', 'currentApprovedRevision.lines']);

        return view('livewire.mechanic.job-screen', [
            'order' => $order,
            'media' => $order->media()->latest()->get(),
            'doneLineIds' => $order->repairItems->pluck('estimate_line_id')->filter()->all(),
        ]);
    }
}
