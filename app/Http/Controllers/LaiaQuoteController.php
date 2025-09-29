<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LaiaQuoteController extends Controller
{
    /**
     * Crea una nueva cotización a partir de los datos enviados por el frontend.
     *
     * Este método valida los campos básicos de la solicitud, genera un número
     * de cotización aleatorio con el prefijo "COT-" y crea tanto la
     * cotización como el ítem principal asociado.  Se devuelve un JSON
     * indicando el éxito y los datos de la cotización creada.
     */
    public function storeQuote(Request $request)
    {
        $data = $request->validate([
            'tipo_servicio'    => 'required|string',
            'proyecto'         => 'required|string',
            'nit'              => 'required|string',
            'email_cliente'    => 'required|email',
            'direccion_obra'   => 'required|string',
            'tipo_obra'        => 'required|string',
            'localidad'        => 'required|string',
            'fecha_aforo_deseada' => 'required|date',
            'nombre_empresa'   => 'required|string',
            'celular_cliente'  => 'required|string',
        ]);

        // Para fines de ejemplo usamos tarifas fijas.
        $amount = $data['tipo_servicio'] === 'PMT' ? 950000 : 650000;
        $requiresMeeting = in_array($data['tipo_servicio'], ['PMT', 'Aforo'], true);

        $quote = Quote::create([
            'number'         => 'COT-' . Str::upper(Str::random(8)),
            'customer_name'  => $data['nombre_empresa'],
            'customer_email' => $data['email_cliente'],
            'status'         => 'draft',
            'valid_until'    => Carbon::now('America/Bogota')->addDays(7),
            'subtotal'       => $amount,
            'tax_total'      => 0,
            'discount_total' => 0,
            'total'          => $amount,
            'meta'           => [
                'proyecto'            => $data['proyecto'],
                'nit'                 => $data['nit'],
                'direccion_obra'      => $data['direccion_obra'],
                'tipo_obra'           => $data['tipo_obra'],
                'localidad'           => $data['localidad'],
                'fecha_aforo_deseada' => $data['fecha_aforo_deseada'],
                'celular_cliente'     => $data['celular_cliente'],
                'tipo_servicio'       => $data['tipo_servicio'],
            ],
        ]);

        QuoteItem::create([
            'quote_id'    => $quote->id,
            'description' => $data['tipo_servicio'] . ' - ' . $data['proyecto'],
            'quantity'    => 1,
            'unit_price'  => $amount,
            'tax_rate'    => 0,
            'line_total'  => $amount,
        ]);

        return response()->json([
            'ok'      => true,
            'message' => 'Cotización creada correctamente',
            'data'    => [
                'quote_id'        => $quote->id,
                'amount'          => $amount,
                'currency'        => 'COP',
                'service'         => $data['tipo_servicio'],
                'requires_meeting'=> $requiresMeeting,
            ],
        ]);
    }

    /**
     * Genera documentos PDF de PMT para una cotización dada.
     *
     * Este método simula la generación de documentos y devuelve un listado
     * con las URLs donde podrían descargarse dichos documentos.
     */
    public function generatePmtDocs(Request $request)
    {
        $data = $request->validate([
            'quote_id' => 'required',
            'inputs'   => 'required|array',
        ]);

        $quoteId = $data['quote_id'];
        $docs = [
            [
                'name' => 'Ficha_Tecnica_PMT_' . $quoteId . '.pdf',
                'url'  => url('/storage/docs/' . $quoteId . '/ficha.pdf'),
            ],
            [
                'name' => 'Plano_Senalizacion_' . $quoteId . '.pdf',
                'url'  => url('/storage/docs/' . $quoteId . '/plano.pdf'),
            ],
        ];

        return response()->json([
            'ok'      => true,
            'message' => 'Documentos generados',
            'data'    => ['docs' => $docs],
        ]);
    }
}