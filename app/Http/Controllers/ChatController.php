<?php

namespace App\Http\Controllers;

use App\Models\Dialog;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Services\AgentService;
use App\Services\QuoteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Throwable;

class ChatController extends Controller
{
    /**
     * Vista principal del chat. Limpia el historial y el contexto de la sesión.
     */
    public function showChat()
    {
        session()->forget([
            'gemini_chat_history',

            // contexto de cotización / flujo
            'laia.aforo_file_disk',
            'laia.aforo_file_path',
            'laia.invoice_id',
            'laia.services',
            'laia.quote_id',
            'laia.quote_number',
            'laia.quote_amount',
            'laia.quote_currency',

            // datos del cliente/proyecto
            'laia.customer_name',
            'laia.customer_email',
            'laia.company_name',
            'laia.project_address',
        ]);

        return view('chat');
    }

    /**
     * Historial de diálogos (paginado).
     */
    public function history()
    {
        $dialogs = Dialog::orderByDesc('id')->paginate(20);
        return view('chat-history', compact('dialogs'));
    }

    /**
     * Envía el mensaje del usuario al Agente (Gemini) y devuelve la respuesta.
     */
    public function send(Request $request, AgentService $agentService)
    {
        $data = $request->validate(['query' => 'required|string|max:4000']);
        $userInput = $data['query'];

        // LOG INICIAL: para confirmar que la ruta se dispara
        \Log::info('CHAT_SEND_HIT', mask_sensitive(['input' => $userInput]));

        try {
            $chatHistory = session('gemini_chat_history', []);

            $result         = $agentService->getResponse($userInput, $chatHistory);
            $answer         = trim((string)($result['response'] ?? ''));
            $updatedHistory = $result['history']  ?? $chatHistory;

            if ($answer === '') {
                $answer = "Listo. ¿Deseas que te envíe la cotización estimada y continuar al pago para generar los documentos de radicación?";
            }

            session(['gemini_chat_history' => $updatedHistory]);

            // Que un fallo de BD no tumbe la respuesta
            try {
                \App\Models\Dialog::create([
                    'user_query' => $userInput,
                    'response'   => $answer,
                ]);
            } catch (\Throwable $e) {
                \Log::warning('DIALOG_SAVE_FAILED', mask_sensitive([
                    'msg' => substr($e->getMessage(), 0, 200),
                    'exception' => get_class($e),
                ]));
            }

            \Log::info('CHAT_SEND_OUTPUT', mask_sensitive(['answer' => $answer]));

            return response()->json(
                ['ok' => true, 'response' => $answer],
                200,
                [],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );

        } catch (\Throwable $e) {
            \Log::error('CHAT_SEND_ERROR', mask_sensitive([
                'message' => substr($e->getMessage(), 0, 200),
                'exception' => get_class($e),
                'trace'   => substr($e->getTraceAsString(), 0, 2000),
            ]));

            return response()->json(
                ['ok' => false, 'response' => 'Lo siento, ocurrió un error inesperado.'],
                500,
                [],
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
    }

    /**
     * Subida de archivo de aforos:
     * - Guarda XLS/XLSX en disco 'public/aforos'
     * - Lee servicios/cliente/dirección desde sesión
     * - Construye ítems con QuoteService (respeta combos/prorrateo)
     * - Crea Quote + QuoteItems con subtotal/IVA/total
     * - Persiste contexto en sesión y registra Dialog
     * - Devuelve link PDF + botón "Simular pago"
     */
    public function uploadAforos(Request $request, QuoteService $quotes)
    {
        $request->validate([
            'aforo_file' => 'required|file|mimes:xls,xlsx|max:10240',
        ]);

        try {
            // 1) Guardar archivo en disco 'public'
            $storedPath   = $request->file('aforo_file')->store('aforos', 'public');
            $aforoDisk    = 'public';
            $aforoRelPath = $storedPath;

            // 2) Recuperar servicios desde sesión; fallback a SVC_COLAS si viniera vacío
            $services = array_values(array_filter(
                (array) session('laia.services', session('gemini_selected_services', []))
            ));
            if (empty($services)) {
                $services = ['SVC_COLAS'];
            }
            session(['laia.services' => $services]);

            // 3) Construir ítems (con prorrateo de combo si aplica)
            $build    = $quotes->buildItems($services);
            $items    = $build['items'];
            $subtotal = (float)($build['totals']['subtotal'] ?? 0);
            $taxRate  = (float)($build['totals']['tax_rate'] ?? 0);
            $taxTotal = round($subtotal * $taxRate, 2);
            $total    = round($subtotal + $taxTotal, 2);
            $currency = $build['totals']['currency'] ?? 'COP';
            $symbol   = $build['totals']['symbol'] ?? '$';

            // 4) Crear Quote (con nombre y dirección del cliente)
            $customerName   = session('laia.customer_name') ?: session('laia.company_name') ?: 'Cliente';
            $customerEmail  = session('laia.customer_email') ?: null;
            $projectAddress = session('laia.project_address') ?: null;

            $quote = new Quote();
            $quote->number         = 'COT-' . Str::upper(Str::random(6));
            $quote->customer_name  = $customerName;
            $quote->customer_email = $customerEmail;
            $quote->status         = 'draft';
            $quote->valid_until    = Carbon::now('America/Bogota')->addDays(7);
            $quote->subtotal       = $subtotal;
            $quote->tax_total      = $taxTotal;
            $quote->discount_total = 0.0;
            $quote->total          = $total;
            $quote->meta           = [
                'services' => $services,
                'currency' => $currency,
                'symbol'   => $symbol,
                'company'  => session('laia.company_name'),
                'address'  => $projectAddress,
            ];
            $quote->save();

            // 4b) Items por cada servicio (usando el prorrateo)
            foreach ($items as $it) {
                QuoteItem::create([
                    'quote_id'    => $quote->id,
                    'description' => $it['description'],
                    'quantity'    => 1,
                    'unit_price'  => $it['unit_price'],
                    'tax_rate'    => $taxRate,
                    'line_total'  => $it['line_total'],
                ]);
            }

            // 5) Guardar contexto en sesión (para PDF / pago / otros pasos)
            $invoiceId = 'INV-' . Str::upper(Str::random(6));
            session([
                'laia.aforo_file_disk' => $aforoDisk,
                'laia.aforo_file_path' => $aforoRelPath,
                'laia.invoice_id'      => $invoiceId,
                'laia.services'        => $services,
                'laia.quote_id'        => $quote->id,
                'laia.quote_number'    => $quote->number,
                'laia.quote_amount'    => $total,
                'laia.quote_currency'  => $currency,
            ]);

            // 6) Persistir también en Dialog (para trazabilidad)
            $context = [
                'services'        => $services,
                'aforo_file_disk' => $aforoDisk,
                'aforo_file_path' => $aforoRelPath,
                'invoice_id'      => $invoiceId,
                'quote'           => [
                    'id'       => $quote->id,
                    'number'   => $quote->number,
                    'amount'   => $total,
                    'currency' => $currency,
                ],
                'state'           => 'quote_generated',
            ];

            try {
                Dialog::create([
                    'user_query'      => 'Subida de aforos',
                    'response'        => 'Cotización generada (PDF disponible).',
                    'session_context' => $context,
                ]);
            } catch (\Throwable $e) {
                Log::warning('DIALOG_SAVE_FAILED_UPLOAD', mask_sensitive([
                    'msg' => substr($e->getMessage(), 0, 200),
                    'exception' => get_class($e),
                ]));
            }

            // 7) Respuesta al frontend (sin mostrar montos en el chat)
            $labels = [];
            foreach ($services as $code) {
                $label = config("laia.services.$code.label") ?: config("laia.services.$code.name");
                $labels[] = $label ?: $code;
            }

            $pdfUrl = route('quotes.downloadPdf', $quote);

            $msg  = "### 🧾 Servicios confirmados\n";
            foreach ($labels as $lbl) {
                $msg .= "- " . e($lbl) . "\n";
            }
            $msg .= "\n";
            $msg .= "Listo. Ya generé tu cotización. Descárgala y revísala.\n\n";
            $msg .= "<a href=\"".e($pdfUrl)."\" target=\"_blank\" class=\"download-btn\">Descargar cotización (PDF)</a>\n\n";
            // IMPORTANTE: enviamos disco + path relativo para que PaymentController use Storage::disk()
            $msg .= "<br><br><button id=\"simular-pago-btn\""
                 .  " data-file-disk=\"".e($aforoDisk)."\""
                 .  " data-file-path=\"".e($aforoRelPath)."\""
                 .  " data-invoice-id=\"".e($invoiceId)."\"">Simular pago y generar documentos</button>";

            return response()->json(['ok' => true, 'response' => $msg]);

        } catch (Throwable $e) {
            Log::error('CHAT_UPLOAD_AFOROS_ERROR', mask_sensitive([
                'message' => substr($e->getMessage(), 0, 200),
                'exception' => get_class($e),
                'trace'   => substr($e->getTraceAsString(), 0, 2000),
            ]));

            return response()->json([
                'ok'       => false,
                'response' => 'No pude procesar el archivo de aforos. Intenta nuevamente.',
            ], 500);
        }
    }
}