<?php

namespace App\Services;

use App\Models\ManualPaymentApproval;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualPaymentApprovalService
{
    public function __construct(
        private readonly PaymentCompletionService $completion,
        private readonly AuditLogger $audit
    ) {}

    public function approve(Order $order, User $admin, array $data, ?Request $request = null): Order
    {
        if ($order->payment_status === 'refunded') {
            throw ValidationException::withMessages(['order' => ['Refunded orders cannot be manually approved.']]);
        }

        if ((int) $data['amount_minor'] !== (int) $order->total_minor) {
            throw ValidationException::withMessages(['amount_minor' => ['Manual approval amount must match the order total.']]);
        }

        if (strtoupper((string) $data['currency']) !== strtoupper((string) $order->currency)) {
            throw ValidationException::withMessages(['currency' => ['Manual approval currency must match the order currency.']]);
        }

        if ($order->payment_status === 'paid') {
            return $order->fresh(['items', 'entitlements.product.files', 'paymentTransactions', 'manualPaymentApproval.approver']);
        }

        return DB::transaction(function () use ($order, $admin, $data, $request) {
            $approval = ManualPaymentApproval::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'approved_by_user_id' => $admin->id,
                    'amount_minor' => $order->total_minor,
                    'currency' => strtoupper((string) $order->currency),
                    'payment_method' => $data['payment_method'],
                    'reference' => $data['reference'] ?? null,
                    'reason' => $data['reason'],
                    'approved_at' => now(),
                    'metadata' => ['source' => 'admin_manual'],
                ]
            );

            $paid = $this->completion->markPaid($order, 'admin_manual', 'admin-manual-payment:'.$order->uuid, [
                'state' => 'paid',
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'provider_transaction_id' => $data['reference'] ?? 'manual-'.$order->order_number,
                'manual_approval' => true,
                'approved_by_user_id' => $admin->id,
                'manual_payment_approval_id' => $approval->id,
                'payment_method' => $data['payment_method'],
                'reason' => $data['reason'],
            ]);

            $this->audit->log('order.payment_manually_approved', $order, [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'admin_user_id' => $admin->id,
                'amount_minor' => $order->total_minor,
                'currency' => $order->currency,
                'method' => $data['payment_method'],
                'reference' => $data['reference'] ?? null,
                'reason' => $data['reason'],
                'timestamp' => now()->toISOString(),
            ], $request);

            return $paid->fresh(['items', 'entitlements.product.files', 'paymentTransactions', 'manualPaymentApproval.approver']);
        });
    }
}
