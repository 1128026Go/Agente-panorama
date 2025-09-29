<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Controlador para la integración con el servicio de pagos de LAIA.
 *
 * En este ejemplo se simula la generación de enlaces de pago para
 * ePayco.  Cuando estés listo para usar la pasarela real, deberás
 * reemplazar la URL de sandbox por la API oficial de ePayco y
 * gestionar los parámetros correspondientes.  El método devuelve
 * un JSON con la URL del pago y un identificador único generado.
 */
class LaiaPaymentController extends Controller
{
    public function createLink(Request $r)
    {
        $data = $r->validate([
            'quote_id'   => 'required',
            'return_url' => 'required|url',
            'cancel_url' => 'required|url',
        ]);

        // En un entorno real deberías integrar el SDK de ePayco.
        $paymentRef = 'PAY-' . uniqid();
        $url        = 'https://secure.epayco.co/checkout/sandbox/' . $paymentRef;

        return response()->json([
            'ok'      => true,
            'message' => 'Enlace de pago creado',
            'data'    => [
                'payment_url' => $url,
                'payment_ref' => $paymentRef,
                'provider'    => 'epayco',
            ],
        ]);
    }
}