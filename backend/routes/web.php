<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppWebhookController;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| WhatsApp Business API Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from Meta WhatsApp Business API.
| They are publicly accessible (no authentication) as Meta calls them directly.
|
*/

// Webhook verification (required by Meta) - GET request
Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);

// Handle incoming WhatsApp messages and status updates from Meta - POST request
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handleIncomingMessage']);
