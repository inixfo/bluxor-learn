<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\RefundAttempt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentRefundService
{
    public function __construct(
        private readonly PipraPayGateway $gateway,
        private readonly AuditLogger $audit
    ) {}

    public function fullRefund(Order $order, ?Request $request = null): RefundAttempt
    {
        $attempt = DB::transaction(function () use ($order, $request) {
            $order = Order::with('entitlements')->lockForUpdate()->findOrFail($order->id);

            if ($order->payment_status !== 'paid') {
                throw ValidationException::withMessages(['order' => ['Only paid orders can be refunded.']]);
            }

            $existing = RefundAttempt::where('order_id', $order->id)
                ->where('refund_type', 'full')
                ->whereIn('status', ['succeeded', 'processing'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $payment = PaymentTransaction::where('order_id', $order->id)
                ->where('gateway', 'piprapay')
                ->where('status', 'paid')
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = RefundAttempt::create([
                'uuid' => (string) Str::uuid(),
                'order_id' => $order->id,
                'payment_transaction_id' => $payment->id,
                'gateway' => 'piprapay',
                'idempotency_key' => 'full:'.$order->uuid,
                'provider_payment_id' => $payment->provider_transaction_id,
                'refund_type' => 'full',
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'status' => 'processing',
                'requested_by' => $request?->user()?->id,
                'requested_at' => now(),
            ]);

            return $attempt;
        });

        try {
            $refund = $this->gateway->refund($order->fresh(), (string) $attempt->provider_payment_id);
        } catch (\Throwable $exception) {
            $attempt->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'raw_response' => ['message' => $exception->getMessage()],
            ])->save();

            throw $exception;
        }

        return DB::transaction(function () use ($order, $request, $attempt, $refund) {
            $order = Order::with('entitlements')->lockForUpdate()->findOrFail($order->id);
            $payment = PaymentTransaction::where('order_id', $order->id)
                ->where('gateway', 'piprapay')
                ->lockForUpdate()
                ->firstOrFail();

            $attempt->forceFill([
                'provider_refund_id' => $refund['provider_refund_id'],
                'status' => 'succeeded',
                'succeeded_at' => now(),
                'raw_response' => $refund['raw'],
            ])->save();

            $previousOrderStatus = $order->order_status;
            $order->forceFill(['payment_status' => 'refunded', 'order_status' => 'refunded'])->save();

            DB::table('order_status_histories')->insert([
                'order_id' => $order->id,
                'from_status' => $previousOrderStatus,
                'to_status' => 'refunded',
                'actor_user_id' => $request?->user()?->id,
                'reason' => 'Full refund confirmed by PipraPay',
                'metadata' => json_encode(['refund_attempt_id' => $attempt->id, 'provider_refund_id' => $refund['provider_refund_id']]),
                'created_at' => now(),
            ]);

            $payment->forceFill([
                'status' => 'refunded',
                'normalized_state' => 'refunded',
                'raw_response' => array_merge($payment->raw_response ?: [], ['refund' => $refund['raw']]),
            ])->save();

            foreach ($order->entitlements as $entitlement) {
                if ($entitlement->status === 'active') {
                    $entitlement->forceFill([
                        'status' => 'revoked',
                        'revoked_at' => now(),
                        'revocation_reason' => 'full_refund',
                        'revocation_reference' => $attempt->uuid,
                    ])->save();
                    $this->audit->log('entitlement.revoked', $entitlement, ['reason' => 'full_refund', 'refund_attempt_id' => $attempt->id], $request);
                }
            }

            $this->audit->log('order.refunded', $order, ['refund_attempt_id' => $attempt->id, 'provider_refund_id' => $refund['provider_refund_id']], $request);

            return $attempt->fresh();
        });
    }
}
