<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TwilioWebhookController;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Twilio Webhook Routes
|--------------------------------------------------------------------------
|
| These routes handle incoming webhooks from Twilio for WhatsApp messages.
| They are publicly accessible (no authentication) as Twilio calls them directly.
|
*/

// Handle incoming WhatsApp messages from customers
Route::post('/webhook/twilio/incoming', [TwilioWebhookController::class, 'handleIncomingMessage']);

// Handle status callbacks from Twilio (delivered, read, failed, etc.)
Route::post('/webhook/twilio/status', [TwilioWebhookController::class, 'handleStatusCallback']);
