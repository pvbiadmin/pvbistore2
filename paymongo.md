To add Paymongo payment gateway support to the PaymentController we'll need to create the necessary settings, views, and
payment handling methods.

```bash
// First, create a new PaymongoSetting model and migration
// app/Models/PaymongoSetting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymongoSetting extends Model
{
    protected $fillable = [
        'public_key',
        'secret_key',
        'status',
        'mode', // 1 for live, 0 for test
        'currency_rate',
        'currency_name',
    ];

    protected $casts = [
        'status' => 'boolean',
        'mode' => 'boolean',
    ];
}

// In PaymentController, add these new methods:

/**
 * View Payment Paymongo Page
 *
 * @param $order_id
 * @param $payable
 * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
 */
public function paymentPaymongo($order_id, $payable): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
{
    $paymongoSettings = PaymongoSetting::first();
    return view('frontend.pages.payment-paymongo', 
        compact('paymongoSettings', 'order_id', 'payable'));
}

/**
 * Handle payment with Paymongo
 *
 * @return RedirectResponse|Response
 * @throws Exception
 */
public function payWithPaymongo()
{
    try {
        $paymongoSettings = PaymongoSetting::first();
        $setting = GeneralSetting::first();

        if ($paymongoSettings && $paymongoSettings->status === 0) {
            return redirect()->back();
        }

        // Calculate payable amount
        $payableAmount = round(payableTotal(), 2);

        if ($payableAmount <= 0) {
            return redirect()->route('user.payment')->with([
                'message' => 'Invalid amount', 
                'alert-type' => 'error'
            ]);
        }

        // Create initial order
        $order = $this->storeOrder(
            'PAYMONGO',
            0,
            Str::random(10),
            $payableAmount,
            $setting->currency_name
        );

        // Initialize Paymongo client
        $secretKey = $paymongoSettings->mode ? 
            $paymongoSettings->secret_key : 
            'sk_test_' . $paymongoSettings->secret_key;

        $client = new \GuzzleHttp\Client();
        
        // Create payment intent
        $response = $client->post('https://api.paymongo.com/v1/payment_intents', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'amount' => $payableAmount * 100, // Convert to cents
                        'payment_method_allowed' => ['card', 'gcash', 'grab_pay'],
                        'currency' => 'PHP',
                        'description' => 'Order #' . $order->invoice_id,
                        'statement_descriptor' => config('app.name'),
                        'metadata' => [
                            'order_id' => $order->id
                        ]
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if (!isset($result['data']['id'])) {
            throw new Exception('Failed to create payment intent');
        }

        // Create payment method
        $response = $client->post('https://api.paymongo.com/v1/payment_methods', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'type' => 'gcash',
                        'details' => [
                            'client_key' => $result['data']['id']
                        ]
                    ]
                ]
            ]
        ]);

        $methodResult = json_decode($response->getBody(), true);

        if (!isset($methodResult['data']['id'])) {
            throw new Exception('Failed to create payment method');
        }

        // Attach payment method to intent
        $response = $client->post("https://api.paymongo.com/v1/payment_intents/{$result['data']['id']}/attach", [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'payment_method' => $methodResult['data']['id'],
                        'return_url' => route('user.paymongo.success', ['order_id' => $order->id])
                    ]
                ]
            ]
        ]);

        $attachResult = json_decode($response->getBody(), true);

        if (!isset($attachResult['data']['attributes']['next_action']['redirect']['url'])) {
            throw new Exception('Failed to get payment URL');
        }

        // Store payment intent ID in session for verification
        Session::put('paymongo_payment_intent', $result['data']['id']);
        Session::put('paymongo_order_id', $order->id);

        // Redirect to payment page
        return redirect()->away($attachResult['data']['attributes']['next_action']['redirect']['url']);

    } catch (Exception $e) {
        return redirect()->route('user.payment')->with([
            'message' => 'Payment processing failed: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }
}

/**
 * Handle Paymongo payment success
 *
 * @param Request $request
 * @return RedirectResponse
 */
public function paymongoSuccess(Request $request): RedirectResponse
{
    try {
        $paymentIntentId = Session::get('paymongo_payment_intent');
        $orderId = Session::get('paymongo_order_id');

        if (!$paymentIntentId || !$orderId) {
            throw new Exception('Invalid payment session');
        }

        $paymongoSettings = PaymongoSetting::first();
        $secretKey = $paymongoSettings->mode ? 
            $paymongoSettings->secret_key : 
            'sk_test_' . $paymongoSettings->secret_key;

        // Verify payment status
        $client = new \GuzzleHttp\Client();
        $response = $client->get("https://api.paymongo.com/v1/payment_intents/$paymentIntentId", [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if ($result['data']['attributes']['status'] === 'succeeded') {
            $order = Order::find($orderId);
            
            if (!$order) {
                throw new Exception('Order not found');
            }

            // Update order status
            $order->payment_status = 1;
            $order->save();

            // Process rewards and referrals
            ReferralController::referralEntry(['order_id' => $order->id]);
            $this->pointRewards(['order_id' => $order->id]);

            // Clear sessions
            $this->clearSession();
            Session::forget('paymongo_payment_intent');
            Session::forget('paymongo_order_id');

            return redirect()->route('user.payment.success');
        }

        throw new Exception('Payment verification failed');

    } catch (Exception $e) {
        return redirect()->route('user.payment')->with([
            'message' => 'Payment verification failed: ' . $e->getMessage(),
            'alert-type' => 'error'
        ]);
    }
}
```

We'll also need to add the routes for Paymongo in the `web.php` routes file:

```bash
Route::get('payment/paymongo/{order_id}/{payable}', [PaymentController::class, 'paymentPaymongo'])
    ->name('user.payment.paymongo');
Route::post('pay-with-paymongo', [PaymentController::class, 'payWithPaymongo'])
    ->name('user.pay.paymongo');
Route::get('paymongo/success', [PaymentController::class, 'paymongoSuccess'])
    ->name('user.paymongo.success');
```

As well as creating a new view file `resources/views/frontend/pages/payment-paymongo.blade.php`:

```bash
@extends('frontend.layouts.master')
@section('content')
    <div class="payment-info-wrapper">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <div class="payment-info">
                        <h3>Paymongo Payment</h3>
                        <p>Order ID: {{ $order_id }}</p>
                        <p>Amount to Pay: {{ number_format($payable, 2) }}</p>
                        
                        <form action="{{ route('user.pay.paymongo') }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                Proceed to Payment
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
```

We'll also need to create a migration for the PaymongoSetting table:

```bash
php artisan make:migration create_paymongo_settings_table
```

As well as the migration file:

```bash
public function up()
{
    Schema::create('paymongo_settings', function (Blueprint $table) {
        $table->id();
        $table->string('public_key');
        $table->string('secret_key');
        $table->boolean('status')->default(1);
        $table->boolean('mode')->default(0); // 0 for test, 1 for live
        $table->decimal('currency_rate', 8, 2)->default(1.00);
        $table->string('currency_name')->default('PHP');
        $table->timestamps();
    });
}
```

Key features of this implementation:

1. Supports Paymongo payment gateway integration
2. Handles both test and live modes
3. Includes proper error handling and session management
4. Processes payment verification
5. Updates order status upon successful payment
6. Handles rewards and referrals after successful payment
7. Maintains consistency with your existing payment gateway implementations

To use this:

1. Run the migration
2. Add Paymongo credentials in your admin panel
3. Make sure to install Guzzle HTTP client if not already installed:

```bash
composer require guzzlehttp/guzzle
```

Here we modify the implementation to allow users to select their preferred payment method before proceeding to Paymongo
payment.

```bash
namespace App\Http\Controllers\Frontend;

// ... (previous imports remain the same)
use App\Models\PaymongoSetting;

class PaymentController extends Controller
{
    // ... (previous methods remain the same)

    /**
     * View Payment Paymongo Page with payment method selection
     *
     * @param $order_id
     * @param $payable
     * @return View|Application|Factory|\Illuminate\Contracts\Foundation\Application
     */
    public function paymentPaymongo($order_id, $payable): View|Application|Factory|\Illuminate\Contracts\Foundation\Application
    {
        $paymongoSettings = PaymongoSetting::first();
        
        $paymentMethods = [
            'card' => [
                'name' => 'Credit/Debit Card',
                'icon' => 'fa-credit-card',
                'description' => 'Pay securely with your credit or debit card'
            ],
            'gcash' => [
                'name' => 'GCash',
                'icon' => 'fa-wallet',
                'description' => 'Pay using your GCash wallet'
            ],
            'grab_pay' => [
                'name' => 'GrabPay',
                'icon' => 'fa-grab',
                'description' => 'Pay using your GrabPay wallet'
            ],
            'paymaya' => [
                'name' => 'Maya',
                'icon' => 'fa-money-bill',
                'description' => 'Pay using your Maya wallet'
            ]
        ];

        return view('frontend.pages.payment-paymongo', 
            compact('paymongoSettings', 'order_id', 'payable', 'paymentMethods'));
    }

    /**
     * Handle payment with Paymongo
     *
     * @param Request $request
     * @return RedirectResponse|Response
     * @throws Exception
     */
    public function payWithPaymongo(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'payment_method' => 'required|in:card,gcash,grab_pay,paymaya'
            ]);

            $paymongoSettings = PaymongoSetting::first();
            $setting = GeneralSetting::first();

            if ($paymongoSettings && $paymongoSettings->status === 0) {
                return redirect()->back();
            }

            // Calculate payable amount
            $payableAmount = round(payableTotal(), 2);

            if ($payableAmount <= 0) {
                return redirect()->route('user.payment')->with([
                    'message' => 'Invalid amount', 
                    'alert-type' => 'error'
                ]);
            }

            // Create initial order
            $order = $this->storeOrder(
                'PAYMONGO-' . strtoupper($request->payment_method),
                0,
                Str::random(10),
                $payableAmount,
                $setting->currency_name
            );

            // Initialize Paymongo client
            $secretKey = $paymongoSettings->mode ? 
                $paymongoSettings->secret_key : 
                'sk_test_' . $paymongoSettings->secret_key;

            $client = new \GuzzleHttp\Client();

            if ($request->payment_method === 'card') {
                // For card payments, create a payment intent
                return $this->handleCardPayment($client, $secretKey, $order, $payableAmount);
            } else {
                // For e-wallets (GCash, GrabPay, PayMaya), create a payment source
                return $this->handleEWalletPayment($client, $secretKey, $order, $payableAmount, $request->payment_method);
            }
        } catch (Exception $e) {
            return redirect()->route('user.payment')->with([
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }

    /**
     * Handle card payment via Paymongo
     */
    private function handleCardPayment($client, $secretKey, $order, $payableAmount)
    {
        // Create payment intent for card payment
        $response = $client->post('https://api.paymongo.com/v1/payment_intents', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'amount' => $payableAmount * 100, // Convert to cents
                        'payment_method_allowed' => ['card'],
                        'currency' => 'PHP',
                        'description' => 'Order #' . $order->invoice_id,
                        'statement_descriptor' => config('app.name'),
                        'metadata' => [
                            'order_id' => $order->id
                        ]
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if (!isset($result['data']['id'])) {
            throw new Exception('Failed to create payment intent');
        }

        // Store payment intent ID in session
        Session::put('paymongo_payment_intent', $result['data']['id']);
        Session::put('paymongo_order_id', $order->id);

        // Return client key for frontend processing
        return response()->json([
            'client_key' => $result['data']['attributes']['client_key'],
            'payment_intent_id' => $result['data']['id']
        ]);
    }

    /**
     * Handle e-wallet payment via Paymongo
     */
    private function handleEWalletPayment($client, $secretKey, $order, $payableAmount, $paymentMethod)
    {
        // Create a payment source for e-wallet payment
        $response = $client->post('https://api.paymongo.com/v1/sources', [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
            ],
            'json' => [
                'data' => [
                    'attributes' => [
                        'amount' => $payableAmount * 100,
                        'currency' => 'PHP',
                        'redirect' => [
                            'success' => route('user.paymongo.success', ['order_id' => $order->id]),
                            'failed' => route('user.paymongo.failed')
                        ],
                        'type' => $paymentMethod,
                        'billing' => [
                            'name' => Auth::user()->name,
                            'email' => Auth::user()->email,
                        ]
                    ]
                ]
            ]
        ]);

        $result = json_decode($response->getBody(), true);

        if (!isset($result['data']['attributes']['redirect']['checkout_url'])) {
            throw new Exception('Failed to create payment source');
        }

        // Store source ID in session
        Session::put('paymongo_source', $result['data']['id']);
        Session::put('paymongo_order_id', $order->id);

        // Redirect to e-wallet checkout page
        return redirect()->away($result['data']['attributes']['redirect']['checkout_url']);
    }

    /**
     * Handle Paymongo payment success
     */
    public function paymongoSuccess(Request $request): RedirectResponse
    {
        try {
            $orderId = Session::get('paymongo_order_id');
            $paymentIntentId = Session::get('paymongo_payment_intent');
            $sourceId = Session::get('paymongo_source');

            if (!$orderId) {
                throw new Exception('Invalid payment session');
            }

            $paymongoSettings = PaymongoSetting::first();
            $secretKey = $paymongoSettings->mode ? 
                $paymongoSettings->secret_key : 
                'sk_test_' . $paymongoSettings->secret_key;

            $client = new \GuzzleHttp\Client();

            // Verify payment status
            if ($paymentIntentId) {
                // Verify card payment
                $response = $client->get("https://api.paymongo.com/v1/payment_intents/$paymentIntentId", [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
                    ]
                ]);
                
                $result = json_decode($response->getBody(), true);
                $status = $result['data']['attributes']['status'];
            } else if ($sourceId) {
                // Verify e-wallet payment
                $response = $client->get("https://api.paymongo.com/v1/sources/$sourceId", [
                    'headers' => [
                        'Accept' => 'application/json',
                        'Authorization' => 'Basic ' . base64_encode($secretKey . ':')
                    ]
                ]);
                
                $result = json_decode($response->getBody(), true);
                $status = $result['data']['attributes']['status'];
            } else {
                throw new Exception('Invalid payment verification data');
            }

            if ($status === 'succeeded' || $status === 'paid') {
                $order = Order::find($orderId);
                
                if (!$order) {
                    throw new Exception('Order not found');
                }

                // Update order status
                $order->payment_status = 1;
                $order->save();

                // Process rewards and referrals
                ReferralController::referralEntry(['order_id' => $order->id]);
                $this->pointRewards(['order_id' => $order->id]);

                // Clear sessions
                $this->clearSession();
                Session::forget(['paymongo_payment_intent', 'paymongo_source', 'paymongo_order_id']);

                return redirect()->route('user.payment.success');
            }

            throw new Exception('Payment verification failed');

        } catch (Exception $e) {
            return redirect()->route('user.payment')->with([
                'message' => 'Payment verification failed: ' . $e->getMessage(),
                'alert-type' => 'error'
            ]);
        }
    }

    /**
     * Handle Paymongo payment failure
     */
    public function paymongoFailed(): RedirectResponse
    {
        Session::forget(['paymongo_payment_intent', 'paymongo_source', 'paymongo_order_id']);
        
        return redirect()->route('user.payment')->with([
            'message' => 'Payment failed or was cancelled. Please try again.',
            'alert-type' => 'error'
        ]);
    }
}
```

Now, let's create the view for payment method selection:

```bash
@extends('frontend.layouts.master')

@section('title')
    {{ __('Paymongo Payment') }}
@endsection

@section('content')
<div class="payment-info-wrapper py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">{{ __('Select Payment Method') }}</h4>
                    </div>
                    <div class="card-body">
                        <div class="payment-details mb-4">
                            <h5>{{ __('Order Details') }}</h5>
                            <p>{{ __('Order ID') }}: {{ $order_id }}</p>
                            <p>{{ __('Amount to Pay') }}: {{ number_format($payable, 2) }} PHP</p>
                        </div>

                        <div class="payment-methods">
                            <form id="payment-form" action="{{ route('user.pay.paymongo') }}" method="POST">
                                @csrf
                                <div class="row">
                                    @foreach($paymentMethods as $key => $method)
                                        <div class="col-md-6 mb-3">
                                            <div class="payment-method-card">
                                                <input type="radio" class="btn-check" name="payment_method" 
                                                       id="method-{{ $key }}" value="{{ $key }}" 
                                                       autocomplete="off" required>
                                                <label class="btn btn-outline-primary w-100 h-100 p-3" 
                                                       for="method-{{ $key }}">
                                                    <div class="d-flex align-items-center mb-2">
                                                        <i class="fas {{ $method['icon'] }} fa-2x me-2"></i>
                                                        <h5 class="mb-0">{{ $method['name'] }}</h5>
                                                    </div>
                                                    <p class="text-muted small mb-0">
                                                        {{ $method['description'] }}
                                                    </p>
                                                </label>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>

                                <div class="text-center mt-4">
                                    <button type="submit" class="btn btn-primary btn-lg px-5">
                                        {{ __('Proceed to Payment') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($paymongoSettings->mode == 0)
    <div class="alert alert-warning text-center mt-3">
        <i class="fas fa-info-circle me-2"></i>
        {{ __('Test Mode is enabled. Use test card details for payment.') }}
    </div>
@endif
@endsection

@push('styles')
<style>
    .payment-method-card {
        height: 100%;
    }
    .btn-check:checked + .btn-outline-primary {
        border-width: 2px;
    }
    .payment-details {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
    }
</style>
@endpush

@push('scripts')
<script>
document.getElementById('payment-form').addEventListener('submit', function(e) {
    const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
    if (!selectedMethod) {
        e.preventDefault();
        alert('Please select a payment method');
    }
});
</script>
@endpush
```

As well as updating the routes:

```bash
Route::get('payment/paymongo/{order_id}/{payable}', [PaymentController::class, 'paymentPaymongo'])
    ->name('user.payment.paymongo');
Route::post('pay-with-paymongo', [PaymentController::class, 'payWithPaymongo'])
    ->name('user.pay.paymongo');
Route::get('paymongo/success', [PaymentController::class, 'paymongoSuccess'])
    ->name('user.paymongo.success');
Route::get('paymongo/failed', [PaymentController::class, 'paymongoFailed'])
    ->name('user.paymongo.failed');
```
