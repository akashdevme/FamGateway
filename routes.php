<?php

use Illuminate\Support\Facades\Route;
use App\Extensions\Gateways\FamGateway\Controllers\FamGatewayController;

Route::prefix('extensions/gateways/famgateway')->group(function () {
    // 1. External Webhook receiver endpoint from FamGateway
    $webhookRoute = Route::post('/webhook', [FamGatewayController::class, 'handleWebhook']);

    // Safely exclude CSRF verification for server-to-server webhook callbacks across Laravel versions
    if (class_exists(\App\Http\Middleware\VerifyCsrfToken::class)) {
        $webhookRoute->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    } elseif (class_exists(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class)) {
        $webhookRoute->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    }

    // 2. Client return landing redirect
    Route::get('/return', [FamGatewayController::class, 'handleReturn'])
        ->name('extensions.gateways.famgateway.return');
});
