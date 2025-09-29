<?php

use App\Http\Controllers\LaiaCalcController;
use App\Http\Controllers\LaiaCalendarController;
use App\Http\Controllers\LaiaPaymentController;
use App\Http\Controllers\LaiaQuoteController;
use App\Http\Controllers\PaymentController;
use App\Http\Middleware\VerifyN8nToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí es donde se registran las rutas para los servicios internos de LAIA.
| El prefijo /api/laia agrupa las rutas que son invocadas por n8n u otros
| microservicios y están protegidas por el middleware VerifyN8nToken.
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('laia')->middleware(VerifyN8nToken::class)->group(function () {
    Route::post('/calc/run',        [LaiaCalcController::class, 'run']);
    Route::post('/quotes/create',   [LaiaQuoteController::class, 'storeQuote']);
    Route::post('/quotes/{id}/generate-docs', [LaiaQuoteController::class, 'generatePmtDocs']);
    Route::post('/payments/create-link', [LaiaPaymentController::class, 'createLink']);
    Route::post('/calendar/schedule', [LaiaCalendarController::class, 'schedule']);

    // Endpoints de simulación de pago y respuestas de pasarela
    Route::post('/pmt/confirm-volante', [PaymentController::class, 'handleConfirmation']);
    Route::post('/pmt/epayco-webhook', [PaymentController::class, 'handleResponse']);
});

// Rutas protegidas con clave de agente (usadas por el frontend)
Route::middleware('agent.auth')->group(function () {
    Route::post('/quotes',        [LaiaQuoteController::class, 'storeQuote']);
    Route::post('/payments/link',[LaiaPaymentController::class, 'createLink']);
    Route::post('/pmt/generate', [LaiaQuoteController::class, 'generatePmtDocs']);
});