<?php

namespace App\Contracts;

use App\Models\Order;

interface PaymentGateway
{
    public function initiate(Order $order): array;

    public function verify(string $providerPaymentId): array;

    public function normalizeVerified(Order $order, array $provider): array;

    public function normalizeFailedPayload(Order $order, array $payload, string $state): array;

    public function validateWebhook(array $payload, ?string $receivedApiKey): array;

    public function refund(Order $order, string $providerPaymentId): array;
}
