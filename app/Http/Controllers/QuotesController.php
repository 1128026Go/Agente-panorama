<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Controlador para descargar cotizaciones en formato PDF.
 */
class QuotesController extends Controller
{
    /**
     * Genera y descarga el PDF para una cotización.
     *
     * Este método carga los ítems asociados a la cotización, prepara los
     * datos para la vista y usa DomPDF para generar el archivo.  La
     * plantilla `quotes.pdf` (no incluida aquí) debe existir bajo
     * resources/views/quotes/pdf.blade.php o similar.
     */
    public function downloadPdf(Quote $quote)
    {
        $quote->load('items');
        $data = [
            'quote'    => $quote,
            'items'    => $quote->items,
            'currency' => $quote->meta['currency'] ?? config('laia.currency', 'COP'),
            'symbol'   => $quote->meta['symbol']   ?? config('laia.currency_symbol', '$'),
            'address'  => $quote->meta['address']  ?? null,
        ];

        $pdf = Pdf::loadView('quotes.pdf', $data)->setPaper('letter');
        return $pdf->download($quote->number . '.pdf');
    }
}