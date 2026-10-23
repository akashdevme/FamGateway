<?php

namespace App\Extensions\Gateways\FamGateway\Controllers;

use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class FamGatewayController extends Controller
{
    /**
     * Resolve the configured FamGateway API Key from Paymenter settings.
     *
     * @return string|null
     */
    protected function getApiKey()
    {
        // 1. Try ExtensionHelper if available
        if (class_exists(ExtensionHelper::class) && method_exists(ExtensionHelper::class, 'getConfig')) {
            $key = ExtensionHelper::getConfig('FamGateway', 'api_key');
            if (!empty($key)) {
                return trim($key);
            }
        }

        // 2. Fallback to direct database query from settings table
        try {
            $setting = Setting::where('key', 'LIKE', '%famgateway%api_key%')
                ->orWhere('key', 'LIKE', '%FamGateway%api_key%')
                ->first();

            if ($setting && !empty($setting->value)) {
                return trim($setting->value);
            }
        } catch (\Throwable $e) {
            // Ignore DB query errors if table schema differs
        }

        return null;
    }

    /**
     * Handle incoming asynchronous webhook notifications dispatched by FamGateway.
     * Endpoint: POST /extensions/gateways/famgateway/webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-FamGateway-Signature') ?? $request->header('x-famgateway-signature');

        Log::info("[FamGateway Webhook] Received webhook payload: " . $rawBody);

        if (empty($signature)) {
            Log::warning("[FamGateway Webhook] Rejected: Missing X-FamGateway-Signature header.");
            return response()->json(['status' => 'error', 'message' => 'Missing signature header'], 401);
        }

        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            Log::critical("[FamGateway Webhook] API Key could not be resolved from Paymenter config.");
            return response()->json(['status' => 'error', 'message' => 'Gateway configuration error'], 500);
        }

        // Cryptographic HMAC-SHA256 Timing-Safe Signature Verification
        $expectedSignature = hash_hmac('sha256', $rawBody, $apiKey);
        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning("[FamGateway Webhook] Cryptographic signature mismatch. Expected: {$expectedSignature}, Received: {$signature}");
            return response()->json(['status' => 'error', 'message' => 'Invalid cryptographic signature'], 401);
        }

        $data = json_decode($rawBody, true);
        if (!$data || !is_array($data)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid JSON payload'], 400);
        }

        $event = $data['event'] ?? '';
        if ($event !== 'payment.success') {
            Log::info("[FamGateway Webhook] Ignored non-payment event: {$event}");
            return response()->json(['status' => 'ignored', 'message' => "Event '{$event}' ignored"], 200);
        }

        $orderId = $data['order_id'] ?? null;
        $amount  = $data['amount'] ?? null;
        $utr     = $data['utr'] ?? $data['transaction_id'] ?? 'UTR_' . time();
        $sender  = $data['sender_name'] ?? 'UPI Customer';

        // 1. Resolve Invoice ID from request query string (attached during order creation)
        $invoiceId = $request->query('invoice_id');

        // 2. Secondary resolution: Check cached session mapping
        if (!$invoiceId && $orderId) {
            $cached = Cache::get('famgateway_order_' . $orderId);
            if (is_array($cached) && isset($cached['invoice_id'])) {
                $invoiceId = $cached['invoice_id'];
            }
        }

        if (!$invoiceId) {
            Log::error("[FamGateway Webhook] Unable to map Order ID {$orderId} to any Paymenter Invoice.");
            return response()->json(['status' => 'error', 'message' => 'Invoice not found for order'], 404);
        }

        $invoice = Invoice::find($invoiceId);
        if (!$invoice) {
            Log::error("[FamGateway Webhook] Invoice #{$invoiceId} does not exist in Paymenter.");
            return response()->json(['status' => 'error', 'message' => "Invoice #{$invoiceId} not found"], 404);
        }

        // Check if invoice is already paid to ensure idempotency
        if ($invoice->status === 'paid') {
            Log::info("[FamGateway Webhook] Invoice #{$invoiceId} is already marked as paid. Skipping.");
            return response()->json(['status' => 'already_paid', 'message' => 'Invoice is already settled'], 200);
        }

        // Mark invoice as paid via Paymenter's official ExtensionHelper
        try {
            if (class_exists(ExtensionHelper::class) && method_exists(ExtensionHelper::class, 'paymentDone')) {
                ExtensionHelper::paymentDone($invoice->id, 'FamGateway', $utr);
            } else {
                // Fallback direct model update
                $invoice->status = 'paid';
                $invoice->save();
            }

            Log::info("[FamGateway Webhook] SUCCESS! Invoice #{$invoiceId} verified & marked as paid. UTR: {$utr}, Sender: {$sender}, Amount: ₹{$amount}");

            return response()->json([
                'status' => 'success',
                'message' => "Invoice #{$invoiceId} successfully settled.",
                'order_id' => $orderId,
                'utr' => $utr
            ], 200);

        } catch (\Exception $e) {
            Log::error("[FamGateway Webhook] Error marking invoice as paid: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error fulfilling invoice: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Handle customer redirect back from FamGateway hosted checkout.
     * Endpoint: GET /extensions/gateways/famgateway/return
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleReturn(Request $request)
    {
        $invoiceId = $request->query('invoice_id');

        if ($invoiceId && $invoice = Invoice::find($invoiceId)) {
            return redirect()->route('clients.invoice.show', $invoice->id)->with('success', 'Thank you! Your payment is being verified.');
        }

        return redirect('/')->with('info', 'Payment completed. Please check your invoices.');
    }
}
