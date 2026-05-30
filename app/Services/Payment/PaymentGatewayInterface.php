<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initie un paiement et retourne au minimum :
     *   ['success' => bool, 'payment_url' => ?string, 'token' => ?string, 'message' => ?string]
     *
     * @param  array{
     *   amount: int,
     *   currency?: string,
     *   reference: string,
     *   description?: string,
     *   type: 'order'|'subscription_payment',
     *   customer?: array<string,mixed>,
     *   items?: array<int,array<string,mixed>>,
     *   custom_data?: array<string,mixed>,
     *   return_url?: string,
     *   cancel_url?: string
     * } $data
     * @return array<string,mixed>
     */
    public function createPayment(array $data): array;

    /**
     * @return array<string,mixed>
     */
    public function checkStatus(string $token): array;

    /**
     * Verifie la signature/integrite d'un callback.
     *
     * @param  array<string,mixed>  $data
     */
    public function verifyCallback(array $data, ?string $rawBody = null, ?string $signatureHeader = null): bool;
}
