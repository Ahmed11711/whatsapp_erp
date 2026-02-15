<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MessageController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;



/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and are prefixed
| with "api". They are intended for stateless API access from the
| React frontend.
|
*/

Route::get('/meta-test', function () {
    return app(\App\Services\MetaWhatsAppService::class)
        ->sendMessage('201068704455', 'Test from server');
});

Route::get('/test', function () {
    return response()->json(['status' => 'ok']);
});


Route::get('/meta/webhook', function () {
    $verify_token = 'K9xT2pLm8QwZ4rNs7VbY1cHd6EfG3uJk';
    if (request()->get('hub_verify_token') === $verify_token) {
        return response(request()->get('hub_challenge'), 200);
    }
    return response('Error, wrong token', 403);
});

// Route::get('/meta/webhook', [\App\Http\Controllers\MetaWebhookController::class, 'verify']);
Route::post('/meta/webhook', [\App\Http\Controllers\MetaWebhookController::class, 'handle']);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware(['auth.api'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Conversations and messages
    Route::get('/messages', [MessageController::class, 'index']); // list all messages for current agent
    Route::get('/conversations', [MessageController::class, 'conversations']); // list conversations grouped by customer
    Route::get('/conversations/{customer}', [MessageController::class, 'show']); // conversation with specific customer
    Route::post('/conversations/{customer}/messages', [MessageController::class, 'store']); // send message
    Route::post('/messages/{message}/read', [MessageController::class, 'markRead']); // mark single message as read
    Route::post('/customers/{customer}/read', [MessageController::class, 'markConversationRead']); // mark conversation as read
    
    Route::get('/meta/status', [MessageController::class, 'checkStatus']); // check WhatsApp status
});
