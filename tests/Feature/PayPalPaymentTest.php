<?php

namespace Tests\Feature;

use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
         $this->withoutMiddleware(
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class
    );

        config([
            'services.paypal.mode' => 'sandbox',
            'services.paypal.client_id' => 'test-client-id',
            'services.paypal.client_secret' => 'test-client-secret',
            'services.paypal.currency' => 'USD',
            'services.paypal.amount' => '49.00',
            'services.paypal.product_name' => 'Premium Demo Subscription',
        ]);
    }

    private function fakeAccessToken(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200),
        ]);
    }

    private function createTestPayment(
        string $orderId = 'TEST-ORDER-123',
        string $status = 'CREATED'
    ): Payment {
        return Payment::create([
            'reference' => 'test-reference-123',
            'paypal_order_id' => $orderId,
            'amount' => '49.00',
            'currency' => 'USD',
            'status' => $status,
        ]);
    }

    // TC-01: Checkout page loads
    public function test_checkout_page_loads_successfully(): void
    {
        $response = $this->get('/payments');

        $response->assertStatus(200);
        $response->assertSee('PayPal');
    }

    // TC-02: PayPal order is created successfully
    public function test_paypal_order_is_created_successfully(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                ], 200),

            'api-m.sandbox.paypal.com/v2/checkout/orders' =>
                Http::response([
                    'id' => 'PAYPAL-ORDER-123',
                    'status' => 'CREATED',
                ], 201),
        ]);

        $response = $this->postJson(route('paypal.create-order'));

        $response->assertStatus(200)
            ->assertJson([
                'id' => 'PAYPAL-ORDER-123',
            ]);

        $this->assertDatabaseHas('payments', [
            'paypal_order_id' => 'PAYPAL-ORDER-123',
            'amount' => '49.00',
            'currency' => 'USD',
            'status' => 'CREATED',
        ]);
    }

    // TC-03: Frontend amount is ignored
    public function test_order_uses_server_configured_amount(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                ], 200),

            'api-m.sandbox.paypal.com/v2/checkout/orders' =>
                Http::response([
                    'id' => 'PAYPAL-ORDER-456',
                    'status' => 'CREATED',
                ], 201),
        ]);

        $this->postJson(route('paypal.create-order'), [
            'amount' => '1.00',
        ])->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_contains(
                $request->url(),
                '/v2/checkout/orders'
            ) && $request['purchase_units'][0]['amount']['value'] === '49.00';
        });
    }

    // TC-04: PayPal order API failure
    public function test_order_creation_returns_502_when_paypal_fails(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                ], 200),

            'api-m.sandbox.paypal.com/v2/checkout/orders' =>
                Http::response([
                    'name' => 'INTERNAL_SERVER_ERROR',
                ], 500),
        ]);

        $response = $this->postJson(route('paypal.create-order'));

        $response->assertStatus(502)
            ->assertJson([
                'message' => 'PayPal order could not be created. Please try again.',
            ]);
    }

    // TC-05: OAuth token failure
    public function test_order_creation_returns_500_when_oauth_fails(): void
    {
        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'error' => 'invalid_client',
                ], 401),
        ]);

        $response = $this->postJson(route('paypal.create-order'));

        $response->assertStatus(500);
    }

    // TC-06: Successful payment capture
    public function test_payment_is_captured_successfully(): void
    {
        $payment = $this->createTestPayment();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                ], 200),

            'api-m.sandbox.paypal.com/v2/checkout/orders/TEST-ORDER-123/capture' =>
                Http::response([
                    'id' => 'TEST-ORDER-123',
                    'status' => 'COMPLETED',
                    'payment_source' => [
                        'paypal' => [
                            'email_address' => 'buyer@example.com',
                        ],
                    ],
                    'purchase_units' => [
                        [
                            'payments' => [
                                'captures' => [
                                    [
                                        'id' => 'CAPTURE-123',
                                        'status' => 'COMPLETED',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 201),
        ]);

        $response = $this->postJson(
            route('paypal.capture-order'),
            ['orderID' => $payment->paypal_order_id]
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'COMPLETED',
                'orderID' => 'TEST-ORDER-123',
                'captureID' => 'CAPTURE-123',
                'payerEmail' => 'buyer@example.com',
            ]);

        $this->assertDatabaseHas('payments', [
            'paypal_order_id' => 'TEST-ORDER-123',
            'status' => 'COMPLETED',
            'capture_id' => 'CAPTURE-123',
        ]);
    }

    // TC-07: Unknown order
    public function test_unknown_order_returns_404(): void
    {
        $response = $this->postJson(
            route('paypal.capture-order'),
            ['orderID' => 'UNKNOWN-ORDER']
        );

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Unknown PayPal order.',
            ]);
    }

    // TC-08: Missing order ID
    public function test_capture_requires_order_id(): void
    {
        $response = $this->postJson(
            route('paypal.capture-order'),
            []
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['orderID']);
    }

    // TC-09: PayPal has not completed capture
    public function test_pending_capture_returns_409(): void
    {
        $payment = $this->createTestPayment();

        Http::fake([
            'api-m.sandbox.paypal.com/v1/oauth2/token' =>
                Http::response([
                    'access_token' => 'test-access-token',
                ], 200),

            'api-m.sandbox.paypal.com/v2/checkout/orders/TEST-ORDER-123/capture' =>
                Http::response([
                    'id' => 'TEST-ORDER-123',
                    'status' => 'PENDING',
                    'purchase_units' => [
                        [
                            'payments' => [
                                'captures' => [
                                    [
                                        'id' => 'CAPTURE-PENDING',
                                        'status' => 'PENDING',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ], 201),
        ]);

        $response = $this->postJson(
            route('paypal.capture-order'),
            ['orderID' => $payment->paypal_order_id]
        );

        $response->assertStatus(409)
            ->assertJson([
                'status' => 'PENDING',
            ]);

        $this->assertDatabaseHas('payments', [
            'paypal_order_id' => 'TEST-ORDER-123',
            'status' => 'PENDING',
        ]);
    }

    // TC-10: Already completed payment is not captured again
    public function test_completed_payment_is_not_captured_twice(): void
    {
        $payment = $this->createTestPayment(
            'TEST-ORDER-123',
            'COMPLETED'
        );

        $payment->update([
            'capture_id' => 'CAPTURE-ALREADY-DONE',
        ]);

        Http::fake();

        $response = $this->postJson(
            route('paypal.capture-order'),
            ['orderID' => $payment->paypal_order_id]
        );

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'COMPLETED',
                'captureID' => 'CAPTURE-ALREADY-DONE',
                'message' => 'This payment was already captured.',
            ]);

        Http::assertNothingSent();
    }
}
