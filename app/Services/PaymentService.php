<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\Payment;
use Illuminate\Support\Str;

/**
 * Servicio para simular el flujo de pagos.  En un entorno de producción
 * deberías usar el SDK de la pasarela de pagos y manejar los webhooks.
 */
class PaymentService
{
    /**
     * Crea una sesión de pago o reutiliza una existente para una cotización.
     */
    public function createPaymentSession(Quote $quote): Payment
    {
        $existing = Payment::where('quote_id', $quote->id)
                           ->where('status', 'created')
                           ->first();
        if ($existing) {
            return $existing;
        }
        return Payment::create([
            'quote_id'   => $quote->id,
            'amount'     => $quote->total,
            'currency'   => 'COP',
            'gateway'    => 'simulated_gateway',
            'status'     => 'created',
            'gateway_ref'=> 'SIM-' . Str::random(10),
        ]);
    }

    /**
     * Marca un pago y su cotización como pagados.  En un flujo real
     * esto se ejecutaría al recibir un webhook de la pasarela.
     */
    public function handleSuccessfulPayment(Payment $payment): bool
    {
        if ($payment->status === 'paid') {
            return true;
        }
        $payment->status = 'paid';
        $payment->save();
        $quote = $payment->quote;
        $quote->status = 'paid';
        $quote->save();
        return true;
    }

    /**
     * Procesa un pago inmediato y devuelve un resultado simulado.
     */
    public function processPayment(float $amount): array
    {
        return [
            'success'        => true,
            'transaction_id' => 'txn_' . Str::random(12),
        ];
    }
}