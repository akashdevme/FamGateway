<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\FamGateway\FamGateway;

Route::post(
    '/extensions/gateways/famgateway/webhook',
    [FamGateway::class, 'webhook']
)->name('extensions.gateways.famgateway.webhook');
