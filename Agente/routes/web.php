<?php

use App\Http\Controllers\ChatController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PaymentsController;
use App\Http\Controllers\QuotesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Estas rutas están destinadas a la interfaz web (Blade).  Incluyen el
| chat del usuario, la subida de aforos, la simulación de pago y la
| descarga de PDFs.  Se cargan con el middleware "web" por defecto.
*/

// Vista de bienvenida
Route::view('/', 'welcome');

// Muestra la vista del chat y prepara la sesión
Route::get('/chat', [ChatController::class, 'showChat'])->name('chat.show');

// Muestra el historial de conversaciones
Route::get('/chat/history', [ChatController::class, 'history'])->name('chat.history');

// Envía un mensaje del usuario al chat
Route::post('/chat/send', [ChatController::class, 'send'])->name('chat.send');

// Sube el archivo de aforos para generar la cotización
Route::post('/upload-aforos', [ChatController::class, 'uploadAforos'])->name('aforos.upload');

// Ejecuta el cálculo final (simula pago)
Route::post('/payment/simulate', [PaymentController::class, 'simulate'])->name('payment.simulate');

// Descarga la cotización generada en formato PDF
Route::get('/quotes/{quote}/pdf', [QuotesController::class, 'downloadPdf'])->name('quotes.downloadPdf');

// Páginas de pago simuladas (para tests manuales)
Route::get('/payment/{payment}', [PaymentsController::class, 'showPaymentPage'])->name('payment.process');
Route::post('/payment/{payment}/confirm', [PaymentsController::class, 'confirmPayment'])->name('payment.confirm');