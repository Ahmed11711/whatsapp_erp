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


Route::get('/meta/webhook', [\App\Http\Controllers\MetaWebhookController::class, 'verify']);
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
