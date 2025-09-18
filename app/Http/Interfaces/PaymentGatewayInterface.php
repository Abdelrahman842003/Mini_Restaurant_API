<?php

namespace App\Http\Interfaces;

use Illuminate\Http\Request;

interface PaymentGatewayInterface
{
    /**
     * Send payment request to gateway
     *
     * @param Request $request
     * @return array
     */
    public function sendPayment(Request $request): array;

    /**
     * Handle payment callback from gateway
     *
     * @param Request $request
     * @return bool
     */
    public function callBack(Request $request): bool;

    /**
     * Get gateway name
     *
     * @return string
     */
    public function getGatewayName(): string;
}

