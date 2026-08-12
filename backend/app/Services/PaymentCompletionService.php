<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\Entitlement;
use App\Jobs\SendPurchaseConfirmationEmail;
use App\Models\Order;
use App\Models\PaymentEvent;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PaymentCompletionService
{
    public function markPaid(Order $order, string $gateway, string $eventKey, array $payload = []): Order
    {
        return DB::transaction(function () use ($order, $gateway, $eventKey, $payload) {
            $event = PaymentEvent::firstOrCreate(
                ['gateway' => $gateway, 'event_key' => $eventKey],
                [
                    'order_id' => $order->id,
                    'provider_transaction_id' => $payload['tran_id'] ?? null,
                    'payload_hash' => hash('sha256', json_encode($payload)),
                    'payload' => $payload,
                ]
            );

            if ($event->processed_at) {
                return $order->fresh(['items', 'entitlements']);
            }

            $order = Order::with('items')->lockForUpdate()->findOrFail($order->id);

            $wasPending = $order->payment_status !== 'paid';

            if ($wasPending) {
                $from = $order->order_status;
                $order->forceFill([
                    'payment_status' => 'paid',
                    'order_status' => 'completed',
                ])->save();

                DB::table('order_status_histories')->insert([
                    'order_id' => $order->id,
                    'from_status' => $from,
                    'to_status' => 'completed',
                    'reason' => 'Payment verified by '.$gateway,
                    'metadata' => json_encode(['event_key' => $eventKey]),
                    'created_at' => now(),
                ]);
            }

            PaymentTransaction::updateOrCreate(
                ['order_id' => $order->id, 'gateway' => $gateway],
                [
                    'uuid' => (string) Str::uuid(),
                    'provider_transaction_id' => $payload['provider_transaction_id'] ?? $payload['tran_id'] ?? $eventKey,
                    'provider_reference' => $payload['validation_id'] ?? $payload['val_id'] ?? null,
                    'validation_id' => $payload['validation_id'] ?? $payload['val_id'] ?? null,
                    'amount_minor' => $payload['amount_minor'] ?? $order->total_minor,
                    'currency' => $payload['currency'] ?? $order->currency,
                    'status' => 'paid',
                    'normalized_state' => $payload['state'] ?? 'paid',
                    'initiated_at' => now(),
                    'paid_at' => now(),
                    'verified_at' => now(),
                    'raw_response' => $payload['raw'] ?? $payload,
                ]
            );

            foreach ($order->items as $item) {
                foreach ($this->productIdsForItem($item) as $productId) {
                    Entitlement::firstOrCreate(
                        ['order_item_id' => $item->id, 'product_id' => $productId],
                        [
                            'uuid' => (string) Str::uuid(),
                            'user_id' => $order->user_id,
                            'order_id' => $order->id,
                            'customer_email' => strtolower($order->customer_email),
                            'status' => 'active',
                            'granted_at' => now(),
                        ]
                    );
                }
            }

            DB::table('analytics_events')->updateOrInsert(
                ['event_uuid' => $order->uuid],
                [
                    'user_id' => $order->user_id,
                    'order_id' => $order->id,
                    'landing_page_id' => $order->metadata['landing_page_id'] ?? null,
                    'landing_page_version_id' => $order->landing_page_version_id,
                    'event_name' => 'purchase',
                    'properties' => json_encode(['total_minor' => $order->total_minor, 'currency' => $order->currency]),
                    'occurred_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            if ($wasPending && $order->coupon_id) {
                DB::table('coupon_usages')->updateOrInsert(
                    ['coupon_id' => $order->coupon_id, 'order_id' => $order->id],
                    [
                        'user_id' => $order->user_id,
                        'customer_email' => strtolower($order->customer_email),
                        'used_at' => now(),
                    ]
                );
            }

            $event->forceFill(['processed_at' => now()])->save();

            if ($wasPending) {
                SendPurchaseConfirmationEmail::dispatch($order->id)->afterCommit();
            }

            return $order->fresh(['items', 'entitlements.product.files']);
        });
    }

    private function productIdsForItem($item): array
    {
        if ($item->product_id) {
            return [$item->product_id];
        }

        if ($item->bundle_id) {
            return Bundle::findOrFail($item->bundle_id)->products()->pluck('products.id')->all();
        }

        throw new InvalidArgumentException('Order item has no entitlement target.');
    }
}
