<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class PayPalPaymentController extends Controller
{
    public function index()
    {
        return view('payments.index', [
            'clientId' => config('services.paypal.client_id'),
            'currency' => config('services.paypal.currency', 'USD'),
            'productName' => config('services.paypal.product_name', 'Premium Demo Subscription'),
            'amount' => config('services.paypal.amount', '49.00'),
        ]);
    }

    public function createOrder(Request $request): JsonResponse
    {
        try {
            $this->assertCredentials();

            $amount = number_format((float) config('services.paypal.amount', 49.00), 2, '.', '');
            $currency = strtoupper(config('services.paypal.currency', 'USD'));
            $reference = (string) Str::uuid();

            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['PayPal-Request-Id' => $reference])
                ->post($this->baseUrl() . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $reference,
                        'description' => config('services.paypal.product_name', 'Premium Demo Subscription'),
                        'amount' => [
                            'currency_code' => $currency,
                            'value' => $amount,
                        ],
                    ]],
                    'payment_source' => [
                        'paypal' => [
                            'experience_context' => [
                                'brand_name' => config('app.name', 'Laravel Demo'),
                                'user_action' => 'PAY_NOW',
                                'return_url' => route('payments.success'),
                                'cancel_url' => route('payments.cancel'),
                            ],
                        ],
                    ],
                ]);

            if (!$response->successful() || !$response->json('id')) {
                report(new \RuntimeException('PayPal create order failed: ' . $response->body()));
                return response()->json(['message' => 'PayPal order could not be created. Please try again.'], 502);
            }

            Payment::create([
                'reference' => $reference,
                'paypal_order_id' => $response->json('id'),
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'CREATED',
            ]);

            return response()->json(['id' => $response->json('id')]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Payment setup failed. Check your PayPal Sandbox credentials and server logs.'], 500);
        }
    }

    public function captureOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderID' => ['required', 'string', 'max:100'],
        ]);

        try {
            $this->assertCredentials();

            $payment = Payment::where('paypal_order_id', $validated['orderID'])->first();
            if (!$payment) {
                return response()->json(['message' => 'Unknown PayPal order.'], 404);
            }

            if ($payment->status === 'COMPLETED') {
                return response()->json([
                    'status' => 'COMPLETED',
                    'orderID' => $payment->paypal_order_id,
                    'captureID' => $payment->capture_id,
                    'message' => 'This payment was already captured.',
                ]);
            }

            $response = Http::withToken($this->accessToken())
                ->acceptJson()
                ->asJson()
                ->withHeaders(['PayPal-Request-Id' => 'capture-' . $payment->reference])
                ->post($this->baseUrl() . '/v2/checkout/orders/' . urlencode($payment->paypal_order_id) . '/capture', (object) []);

            if (!$response->successful()) {
                report(new \RuntimeException('PayPal capture failed: ' . $response->body()));
                return response()->json(['message' => 'PayPal could not capture this payment.'], 502);
            }

            $data = $response->json();
            $capture = data_get($data, 'purchase_units.0.payments.captures.0');
            $captureStatus = data_get($capture, 'status');

            if (($data['status'] ?? null) !== 'COMPLETED' || $captureStatus !== 'COMPLETED') {
                $payment->update(['status' => $data['status'] ?? 'PENDING']);
                return response()->json([
                    'status' => $data['status'] ?? 'PENDING',
                    'message' => 'PayPal has not confirmed a completed capture.',
                ], 409);
            }

            $payment->update([
                'status' => 'COMPLETED',
                'capture_id' => data_get($capture, 'id'),
                'payer_email' => data_get($data, 'payment_source.paypal.email_address'),
                'paypal_response' => $data,
            ]);

            return response()->json([
                'status' => 'COMPLETED',
                'orderID' => $payment->paypal_order_id,
                'captureID' => $payment->capture_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payerEmail' => $payment->payer_email,
                'message' => 'Payment captured and verified by PayPal.',
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Payment verification failed. Please contact support if your account was charged.'], 500);
        }
    }

    public function success(Request $request)
    {
        return view('payments.return', ['result' => 'success']);
    }

    public function cancel()
    {
        return view('payments.return', ['result' => 'cancel']);
    }

    private function assertCredentials(): void
    {
        if (!config('services.paypal.client_id') || !config('services.paypal.client_secret')) {
            throw new \RuntimeException('PayPal Sandbox credentials are missing.');
        }
    }

    private function baseUrl(): string
    {
        return config('services.paypal.mode', 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post($this->baseUrl() . '/v1/oauth2/token', ['grant_type' => 'client_credentials']);

        if (!$response->successful() || !$response->json('access_token')) {
            throw new \RuntimeException('Could not obtain PayPal OAuth access token: ' . $response->body());
        }

        return $response->json('access_token');
    }
}
