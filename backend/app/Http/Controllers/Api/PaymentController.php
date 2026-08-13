<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Services\PaymentCompletionService;
use App\Services\PaymentRefundService;
use App\Services\PipraPayGateway;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentCompletionService $payments,
        private readonly PipraPayGateway $piprapay,
        private readonly PaymentRefundService $refunds
    ) {}

    public function initiatePipraPay(Request $request)
    {
        $data = $request->validate(['order_number' => ['required', 'string']]);
        $order = Order::where('order_number', $data['order_number'])->firstOrFail();

        abort_if($order->payment_status !== 'pending', 422, 'Only pending orders can be sent to payment.');

        return response()->json(['data' => $this->piprapay->initiate($order)]);
    }

    public function success(Request $request)
    {
        $ppId = (string) $request->input('transaction_ref', $request->input('pp_id', $request->input('invoice_id', '')));

        try {
            $provider = $this->piprapay->verify($ppId);
            $this->piprapay->assertPaymentIdMatches($provider, $ppId);
            $order = $this->orderFromProvider($provider, $request->input('order'));
            $verified = $this->piprapay->normalizeVerified($order, $provider);
            $paid = $this->payments->markPaid($order, 'piprapay', 'redirect:'.$verified['provider_transaction_id'], $verified);
        } catch (ValidationException $exception) {
            if ($request->isMethod('get')) {
                return redirect($this->frontendCheckoutResultUrl(null, $ppId, 'unconfirmed'));
            }

            throw $exception;
        }

        if ($request->isMethod('get')) {
            return redirect($this->frontendCheckoutResultUrl($order, $verified['provider_transaction_id'], 'paid'));
        }

        return response()->json(['data' => $paid]);
    }

    public function webhook(Request $request)
    {
        abort_unless(str_contains((string) $request->header('content-type'), 'application/json'), 415);

        $payload = $this->piprapay->validateWebhook($request->all());
        $provider = $this->piprapay->verify((string) $payload['pp_id']);
        $this->piprapay->assertPaymentIdMatches($provider, (string) $payload['pp_id']);
        $order = $this->orderFromProvider($provider);
        $verified = $this->piprapay->normalizeVerified($order, $provider);
        $paid = $this->payments->markPaid($order, 'piprapay', 'webhook:'.$verified['provider_transaction_id'], $verified);

        return response()->json(['data' => $paid]);
    }

    public function failed(Request $request)
    {
        $order = Order::where('order_number', $request->input('order', $request->input('order_number')))->firstOrFail();

        if ($order->payment_status === 'pending') {
            $order->forceFill(['payment_status' => 'failed', 'order_status' => 'failed'])->save();
            PaymentTransaction::where('order_id', $order->id)->where('gateway', 'piprapay')->update([
                'status' => 'failed',
                'normalized_state' => 'cancelled',
                'failed_at' => now(),
            ]);
        }

        return response()->json(['data' => $order]);
    }

    public function refund(Request $request, Order $order)
    {
        $request->validate(['confirm' => ['accepted']]);

        return response()->json(['data' => $this->refunds->fullRefund($order, $request)]);
    }

    private function orderFromProvider(array $provider, ?string $fallbackOrderNumber = null): Order
    {
        $metadata = $this->piprapay->metadata($provider);
        $orderNumber = $metadata['order_number'] ?? $fallbackOrderNumber;

        if (! $orderNumber) {
            $ppId = (string) ($provider['pp_id'] ?? $provider['transaction_id'] ?? '');
            $transaction = PaymentTransaction::where('gateway', 'piprapay')->where('provider_transaction_id', $ppId)->first();
            $orderNumber = $transaction?->order?->order_number;
        }

        if (! $orderNumber) {
            throw ValidationException::withMessages(['metadata' => ['Unable to correlate PipraPay payment to an order.']]);
        }

        return Order::where('order_number', $orderNumber)->firstOrFail();
    }

    private function frontendCheckoutResultUrl(?Order $order, ?string $ppId, string $status): string
    {
        $query = array_filter([
            'order' => $order?->order_number,
            'pp_id' => $ppId,
            'payment_status' => $status,
        ]);

        return rtrim((string) config('app.frontend_url', env('FRONTEND_URL', url('/'))), '/').'/checkout/success?'.http_build_query($query);
    }
}
