<?php

namespace Paymenter\Extensions\Gateways\FamGateway;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use App\Helpers\ExtensionHelper;
use App\Classes\Extension\Gateway;
use Illuminate\Support\Facades\Log;

/**
 * FamGateway Payment Extension for Paymenter
 * 
 * India's 100% Free, Zero-Fee, Non-Custodial Automated UPI Payment Gateway.
 * 
 * @author taravspie
 * @link https://famgateway.in/dashboard.php
 */
class FamGateway extends Gateway
{
    /**
     * Boot the gateway extension and register its routes.
     */
    public function boot()
    {
        require __DIR__ . '/routes/web.php';
    }

    /**
     * Define the configuration fields displayed in the Paymenter Admin Panel.
     *
     * @param array $values
     * @return array
     */
    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'api_key',
                'label' => 'FamGateway API Key',
                'type' => 'text',
                'description' => 'Enter your API key from the FamGateway Dashboard (https://famgateway.in/dashboard.php). Developed by taravspie.',
                'required' => true,
            ],
            [
                'name' => 'currency_check',
                'label' => 'Restrict Currency to INR',
                'type' => 'checkbox',
                'description' => 'Only allow checkout when invoice currency is INR. FamGateway is a zero-fee Indian UPI gateway.',
                'required' => false,
            ],
        ];
    }

    /**
     * Process checkout for the invoice.
     * Creates a dynamic UPI payment session via FamGateway and redirects to the hosted checkout page.
     *
     * @param mixed $invoice
     * @param float $total
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Exception
     */
    public function pay($invoice, $total)
    {
        $currency = $invoice->currency_code ?? $invoice->currency ?? 'INR';
        if ($this->config('currency_check') && strtoupper($currency) !== 'INR') {
            throw new \Exception('The invoice currency code must be "INR" to make payments via FamGateway UPI.');
        }

        $apiKey = trim($this->config('api_key'));
        if (empty($apiKey)) {
            throw new \Exception('FamGateway is not properly configured. Merchant API key is missing. Get your key at https://famgateway.in/dashboard.php');
        }

        $amount = number_format((float)$total, 2, '.', '');
        $customerName = $invoice->user->name ?? 'Customer';
        $customerEmail = $invoice->user->email ?? 'billing@customer.com';
        
        $customerPhone = '9876543210';
        if (method_exists($invoice->user, 'properties') && $invoice->user->properties()->where('key', 'phone')->exists()) {
            $customerPhone = $invoice->user->properties()->where('key', 'phone')->first()->value;
        }

        // Return URL when customer finishes payment
        $returnUrl = route('invoices.show', ['invoice' => $invoice->id]);

        // Webhook URL registered by this extension (includes invoice query param for instant mapping)
        $webhookUrl = route('extensions.gateways.famgateway.webhook', ['invoice_id' => $invoice->id]);

        $payload = [
            'api_key' => $apiKey,
            'amount' => (float)$amount,
            'redirect_url' => $returnUrl,
            'webhook_url' => $webhookUrl,
            'customer_name' => substr($customerName, 0, 50),
            'customer_email' => $customerEmail,
            'customer_phone' => $customerPhone,
        ];

        $client = new Client();

        try {
            $response = $client->post('https://famgateway.in/api/create-order', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Api-Key' => $apiKey,
                ],
                'json' => $payload,
                'timeout' => 15,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['status']) && $data['status'] === 'success' && !empty($data['data']['checkout_url'])) {
                // Redirect user directly to FamGateway's zero-code hosted checkout with 10-min countdown timer & QR
                return redirect()->away($data['data']['checkout_url']);
            }

            $errorMessage = $data['message'] ?? 'Unable to generate dynamic UPI QR code.';
            throw new \Exception('FamGateway error: ' . $errorMessage);

        } catch (\Exception $e) {
            Log::error('[FamGateway] Order creation error: ' . $e->getMessage());
            throw new \Exception('Failed to initialize FamGateway payment: ' . $e->getMessage());
        }
    }

    /**
     * Handle incoming asynchronous webhook from FamGateway with timing-safe HMAC-SHA256 verification.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function webhook(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('x-famgateway-signature') ?? $request->header('X-FamGateway-Signature');

        if (empty($signature)) {
            Log::warning('[FamGateway Webhook] Missing signature header');
            return response('Missing signature header', 401);
        }

        $apiKey = trim($this->config('api_key'));
        if (empty($apiKey)) {
            Log::critical('[FamGateway Webhook] API Key not configured');
            return response('Gateway not configured', 500);
        }

        // Timing-Safe HMAC-SHA256 Cryptographic Check
        $expectedSignature = hash_hmac('sha256', $rawBody, $apiKey);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('[FamGateway Webhook] Signature verification failed');
            return response('Signature verification failed', 401);
        }

        $data = json_decode($rawBody, true);
        if (!$data || !is_array($data)) {
            return response('Invalid JSON payload', 400);
        }

        if (($data['event'] ?? '') === 'payment.success') {
            $orderId = $data['order_id'] ?? null;
            $amount  = (float)($data['amount'] ?? 0);
            $utr     = $data['utr'] ?? $orderId;
            $invoiceId = (int)($request->query('invoice_id') ?? 0);

            if ($invoiceId > 0) {
                // Settle invoice using Paymenter's official ExtensionHelper
                if (method_exists(ExtensionHelper::class, 'addPayment')) {
                    ExtensionHelper::addPayment($invoiceId, 'FamGateway', $amount, null, $utr);
                } elseif (method_exists(ExtensionHelper::class, 'paymentDone')) {
                    ExtensionHelper::paymentDone($invoiceId, 'FamGateway', $utr);
                }

                Log::info("[FamGateway Webhook] Payment confirmed! Invoice #{$invoiceId} settled. UTR: {$utr}, Amount: ₹{$amount}");
                return response('Webhook received and processed successfully', 200);
            }
        }

        return response('Webhook processed', 200);
    }
}
