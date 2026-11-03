<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentReversal;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainException;
use App\Support\Serialized;
use Carbon\CarbonInterface;

/**
 * Manual payment records (cash/bank/external card). A record states what staff observed; it is not
 * a gateway settlement. Settled payments are never deleted; corrections are audited reversals.
 */
class PaymentService
{
    public function record(Invoice $invoice, int $amountMnt, PaymentMethod $method, User $actor, string $idempotencyKey, ?string $reference = null, ?CarbonInterface $receivedAt = null, ?string $note = null): Payment
    {
        $this->assertFinance($actor);

        if ($amountMnt <= 0) {
            throw new DomainException('Төлбөрийн дүн 0-ээс их байна.');
        }

        return Serialized::run('invoice:'.$invoice->work_order_id, function () use ($invoice, $amountMnt, $method, $actor, $idempotencyKey, $reference, $receivedAt, $note): Payment {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                if ($existing->invoice_id !== $invoice->id || $existing->amount_mnt !== $amountMnt) {
                    throw new DomainException('Давхардсан төлбөрийн хүсэлт өөр утгатай байна.');
                }

                return $existing;
            }

            $fresh = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($fresh->status !== InvoiceStatus::Issued) {
                throw new DomainException('Зөвхөн гаргасан нэхэмжлэлд төлбөр бүртгэнэ.');
            }

            $balance = $fresh->balanceMnt();

            if ($amountMnt > $balance) {
                throw new DomainException('Төлбөр үлдэгдлээс ('.number_format($balance, 0, '.', ' ').' ₮) их байна.');
            }

            if ($reference !== null && trim($reference) !== '' && $fresh->payments()->where('reference', trim($reference))->exists()) {
                throw new DomainException('Ижил гүйлгээний дугаартай төлбөр аль хэдийн бүртгэгдсэн.');
            }

            $payment = $fresh->payments()->create([
                'amount_mnt' => $amountMnt,
                'method' => $method,
                'status' => 'settled',
                'reference' => $reference !== null && trim($reference) !== '' ? trim($reference) : null,
                'received_at' => $receivedAt ?? now(),
                'recorded_by' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'note' => $note,
            ]);

            Audit::record('payment.recorded', $payment, ['invoice' => $fresh->id, 'amount' => $amountMnt, 'method' => $method->value], actorId: $actor->id);

            return $payment;
        });
    }

    public function reverse(Payment $payment, int $amountMnt, string $reason, User $actor, string $idempotencyKey): PaymentReversal
    {
        if (! $actor->isOwner()) {
            throw new DomainException('Төлбөр буцаалтыг зөвхөн эзэн бүртгэнэ.');
        }

        if ($amountMnt <= 0 || trim($reason) === '') {
            throw new DomainException('Буцаах дүн ба шалтгааныг оруулна уу.');
        }

        return Serialized::run('invoice:'.$payment->invoice->work_order_id, function () use ($payment, $amountMnt, $reason, $actor, $idempotencyKey): PaymentReversal {
            $existing = PaymentReversal::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                return $existing;
            }

            $fresh = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($fresh->reversedMnt() + $amountMnt > $fresh->amount_mnt) {
                throw new DomainException('Буцаах дүн төлбөрийн үлдсэн дүнгээс их байна.');
            }

            $reversal = $fresh->reversals()->create([
                'amount_mnt' => $amountMnt,
                'reason' => $reason,
                'actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'occurred_at' => now(),
            ]);

            Audit::record('payment.reversed', $fresh, ['amount' => $amountMnt], $reason, $actor->id);

            return $reversal;
        });
    }

    private function assertFinance(User $actor): void
    {
        if (! $actor->isOfficeStaff()) {
            throw new DomainException('Төлбөр бүртгэх эрхгүй байна.');
        }
    }
}
