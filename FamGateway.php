<?php

namespace App\Extensions\Gateways\FamGateway;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FamGateway extends Gateway
{
    /**
     * Get the extension metadata configuration fields.
     *
     * @param array $values
     * @return array
     */
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'api_key',
                'friendly_name' => 'FamGateway API Key',
                'type' => 'text',
                'description' => 'Your merchant API Key from FamGateway Dashboard (e.g. fam_afc0da5b20f8...)',
                'required' => true,
            ],
            [
                'name' => 'currency_check',
                'friendly_name' => 'Restrict Currency to INR',
                'type' => 'boolean',
                'description' => 'Enable to only allow checkout when the invoice currency is INR (FamGateway is 100% Indian UPI).',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'custom_title',
                'friendly_name' => 'Gateway Display Name',
                'type' => 'text',
                'description' => 'Display name shown to clients during checkout.',
                'default' => 'UPI / FamPay / QR Code (Zero Fee)',
                'required' => false,
            ]
        ];
    }

    /**
     * Determine if this payment gateway can be used for the current invoice.
     *
     * @param float $total
     * @param string $currency
     * @param string $type
     * @param array $items
     * @return bool
     */
    public function canUseGateway($total, $currency, $type, $items = [])
    {
        $restrictInr = $this->config('currency_check') ?? true;

        if ($restrictInr && strtoupper($currency) !== 'INR') {
            return false;
        }

        // FamGateway minimum payment is typically ₹1.00
        if ($total < 1.00) {
            return false;
        }

        return true;
    }

    /**
     * Process checkout for the given invoice.
     * Generates a dynamic UPI payment session on FamGateway and returns the checkout URL.
     *
     * @param Invoice $invoice
     * @param float $total
     * @return string
     * @throws \Exception
     */
    public function pay(Invoice $invoice, $total)
    {
        $apiKey = trim($this->config('api_key'));

        if (empty($apiKey)) {
            Log::error("[FamGateway] API key is missing in gateway configuration.");
            throw new \Exception("FamGateway is not properly configured. Merchant API key is missing.");
        }

        // Client and contact information
        $user = $invoice->user;
        $customerName = $user ? ($user->name ?? $user->first_name . ' ' . $user->last_name) : 'Customer';
        $customerEmail = $user ? $user->email : 'billing@client.com';
        $customerPhone = $user->phone ?? '9876543210';

        // Clean amount to 2 decimal places
        $amount = number_format((float)$total, 2, '.', '');

        // Redirect URL after customer completes payment
        $redirectUrl = route('clients.invoice.show', $invoice->id);

        // Webhook URL with invoice reference attached as query parameter for fail-safe reconciliation
        $webhookUrl = url('/extensions/gateways/famgateway/webhook?invoice_id=' . $invoice->id);

        $payload = [
            'api_key' => $apiKey,
            'amount' => (float)$amount,
            'redirect_url' => $redirectUrl,
            'webhook_url' => $webhookUrl,
            'customer_name' => substr($customerName, 0, 50),
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-Api-Key' => $apiKey,
            ])->timeout(15)->post('https://famgateway.in/api/create-order', $payload);

            if (!$response->successful()) {
                Log::error("[FamGateway] Order creation HTTP error: " . $response->body());
                throw new \Exception("FamGateway payment creation failed: " . $response->body());
            }

            $result = $response->json();

            if (!isset($result['status']) || $result['status'] !== 'success' || empty($result['data']['checkout_url'])) {
                $errorMsg = $result['message'] ?? 'Unknown error from FamGateway API';
                Log::error("[FamGateway] API returned error: " . json_encode($result));
                throw new \Exception("Failed to generate UPI payment session: " . $errorMsg);
            }

            $orderData = $result['data'];
            $orderId = $orderData['order_id'];
            $checkoutUrl = $orderData['checkout_url'];

            // Store order mapping in cache for 2 hours for webhook verification
            Cache::put('famgateway_order_' . $orderId, [
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'created_at' => now(),
            ], now()->addHours(2));

            Log::info("[FamGateway] Payment session generated: Order ID {$orderId} for Invoice #{$invoice->id} (Amount: ₹{$amount})");

            // Return FamGateway's zero-code hosted checkout URL (Paymenter redirects user here)
            return $checkoutUrl;

        } catch (\Exception $e) {
            Log::error("[FamGateway] Exception during payment initialization: " . $e->getMessage());
            throw $e;
        }
    }
}
