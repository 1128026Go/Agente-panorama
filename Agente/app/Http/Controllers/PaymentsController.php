<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\Request;

/**
 * Controlador para gestionar el flujo de pagos en la interfaz web.
 *
 * Este controlador crea sesiones de pago y redirige a una página
 * simulada de pasarela.  También muestra la página de pago y la
 * confirmación de transacciones.  En una integración real, la
 * creación y confirmación de pagos se haría a través de webhooks.
 */
class PaymentsController extends Controller
{
    /**
     * Inicia el proceso de pago y redirige al usuario a la página
     * de la pasarela simulada.
     */
    public function redirectToGateway(Quote $quote, PaymentService $paymentService)
    {
        // Crea o reutiliza una sesión de pago para la cotización.
        $payment = $paymentService->createPaymentSession($quote);
        return redirect()->route('payment.process', $payment);
    }

    /**
     * Muestra una página de pago simulada.  Esta vista no existiría
     * en un flujo real de ePayco, pero se utiliza para probar el
     * flujo de la aplicación sin integrarse a una pasarela.
     */
    public function showPaymentPage(Payment $payment)
    {
        return view('payment.process', compact('payment'));
    }

    /**
     * Simula la confirmación de un pago y marca la cotización como pagada.
     */
    public function confirmPayment(Payment $payment, PaymentService $paymentService)
    {
        $paymentService->handleSuccessfulPayment($payment);
        return view('payment.success', ['quote' => $payment->quote]);
    }
}