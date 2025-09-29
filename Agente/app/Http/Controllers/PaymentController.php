<?php

namespace App\Http\Controllers;

use App\Models\Dialog;
use App\Services\AforoValidatorService;
use App\Services\CalculationApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Controlador para gestionar el proceso de simulación de pago y
 * procesamiento de respuestas de la pasarela de pagos.  Este
 * controlador se utiliza principalmente por el chatbot cuando
 * desea ejecutar el cálculo final y confirmar resultados de pago.
 */
class PaymentController extends Controller
{
    /**
     * Simula un pago y ejecuta los cálculos finales con los datos
     * extraídos del archivo de aforos y de la encuesta técnica.
     */
    public function simulate(Request $request, AforoValidatorService $aforoValidator, CalculationApiClient $apiClient)
    {
        Log::info('--- Iniciando simulación de pago ---', mask_sensitive($request->all()));

        $data = $request->validate([
            'aforo_file_path' => 'required|string',
            'invoice_id'     => 'required|string',
            'aforo_file_disk' => 'nullable|string',
        ]);

        try {
            $context = [];
            $lastDialog = Dialog::orderByDesc('created_at')->first();
            if ($lastDialog && is_array($lastDialog->session_context)) {
                $context = $lastDialog->session_context;
            }

            $services = (array) ($context['services'] ?? session('laia.services', []));
            if (empty($services)) {
                throw ValidationException::withMessages(['services' => 'No se encontraron servicios en la sesión.']);
            }

            $disk         = $data['aforo_file_disk']
                ?? ($context['aforo_file_disk'] ?? session('laia.aforo_file_disk', 'public'));
            $relativePath = $data['aforo_file_path']
                ?? ($context['aforo_file_path'] ?? session('laia.aforo_file_path'));

            if (!$relativePath) {
                throw new \Exception('No recibimos la ruta del archivo de aforos.');
            }

            $validation = $aforoValidator->validateAndExtractData($relativePath, $disk);
            if (!($validation['success'] ?? false)) {
                throw new \Exception($validation['message'] ?? 'El archivo de aforos no es válido.');
            }

            $numericalInputs = (array) ($validation['data'] ?? []);

            $muSurvey = session('laia.mu_survey', []);
            if (!empty($muSurvey['mu_h'])) {
                $numericalInputs['mu_h'] = (float) $muSurvey['mu_h'];
            } elseif (empty($numericalInputs['mu_h'])) {
                throw new \Exception('Falta la capacidad de la vía (mu_h). Completa la encuesta técnica antes de simular el pago.');
            }

            // Solo usamos el primer servicio para este ejemplo.
            $primaryService     = $services[0];
            $calculationResult = $apiClient->run($primaryService, $numericalInputs);
            Log::info('Respuesta API de cálculos', mask_sensitive($calculationResult));

            if (!($calculationResult['ok'] ?? false)) {
                $errorDetails = $calculationResult['data']['error'] ?? ($calculationResult['details'] ?? 'Sin detalles');
                throw new \Exception('La API de cálculos devolvió un error: ' . $errorDetails);
            }

            $outputs = $calculationResult['data']['outputs'] ?? [];
            $message = $outputs['OUT_MSG'] ?? 'Cálculo completado.';

            return response()->json([
                'ok'      => true,
                'message' => 'Análisis completado. Resultado: ' . $message,
                'data'    => null,
            ]);
        } catch (Throwable $e) {
            Log::error('SIMULATE_PAYMENT_ERROR', mask_sensitive([
                'message'    => substr($e->getMessage(), 0, 200),
                'exception'  => get_class($e),
                'trace'      => substr($e->getTraceAsString(), 0, 2000),
            ]));

            return response()->json([
                'ok'      => false,
                'message' => 'Ocurrió un problema al realizar los cálculos. Contacta al administrador.',
                'data'    => null,
            ], 500);
        }
    }

    /**
     * Recibe la respuesta de la pasarela de pagos y la registra.  Este método
     * simula el procesamiento de un webhook de pasarela.
     */
    public function handleResponse(Request $request)
    {
        return response()->json([
            'ok'      => true,
            'message' => 'Respuesta de la pasarela recibida. Procesando.',
            'data'    => $request->all(),
        ], 200);
    }

    /**
     * Procesa la confirmación del pago recibido desde la pasarela.
     */
    public function handleConfirmation(Request $request)
    {
        Log::info('Webhook de pasarela recibido', mask_sensitive($request->all()));
        return response()->json([
            'ok'      => true,
            'message' => 'Confirmación del webhook recibida',
            'data'    => $request->all(),
        ], 200);
    }
}