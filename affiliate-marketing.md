<p>Here is a walk-through of the entire process from the initial problem statement to the formulation of the solution for the affiliate marketing system in Laravel 11.</p>

### 1. Problem Statement:

The task was to create an affiliate marketing system in Laravel 11 with the following requirements:

- Users can share affiliate links to other users.
- When the link is clicked, it redirects to a product details page, if user was logged in, otherwise the user will be
  required to log in first.
- The seller (affiliate) who owns the link earns a percentage of the item's price.
- The buyer who clicks the link receives a percentage discount on the item's price.

### 2. Analysis and Planning:

Based on the requirements, we identified the key components needed:

- User management (for both affiliates and buyers)
- Product management
- Affiliate link generation and tracking
- Order processing with discount and commission calculation

### 3. Database Design:

We designed the database schema, including tables for:

- Users (including affiliate information)
- Products (with pricing and affiliate percentages)
- Affiliate Links (to track links and clicks)
- Orders (to record sales, discounts, and commissions)

***database/migrations/xxxx_xx_xx_create_affiliate_links_table.php***

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAffiliateLinksTable extends Migration
{
    public function up()
    {
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->string('code')->unique();
            $table->integer('clicks')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('affiliate_links');
    }
}
```

***database/migrations/xxxx_xx_xx_create_orders_table.php***

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('affiliate_link_id')->nullable()->constrained();
            $table->decimal('total_amount', 10, 2);
            $table->decimal('discount_amount', 10, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}
```

### 4. Model Creation:

We created Laravel models to interact with these database tables:

- User
- Product
- AffiliateLink
- Order

***app/Models/User.php***

```php
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'affiliate_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class);
    }

    public function generateAffiliateCode()
    {
        do {
            $code = substr(md5(uniqid(mt_rand(), true)), 0, 10);
        } while (static::where('affiliate_code', $code)->exists());

        $this->affiliate_code = $code;
        $this->save();
    }
}
```

***app/Models/AffiliateLink.php***

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AffiliateLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'code',
        'clicks',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
```

***app/Models/Product.php***

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'price',
        'affiliate_discount_percentage',
        'affiliate_commission_percentage',
    ];

    public function affiliateLinks()
    {
        return $this->hasMany(AffiliateLink::class);
    }
}
```

***app/Models/Order.php***

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'affiliate_link_id',
        'total_amount',
        'discount_amount',
        'commission_amount',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function affiliateLink()
    {
        return $this->belongsTo(AffiliateLink::class);
    }
}
```

### 5. Controller Logic:

We implemented controllers to handle the main functionalities:

- AffiliateController: For generating affiliate links and handling redirects
- CheckoutController: For processing orders and applying discounts/commissions

***app/Http/Controllers/AffiliateController.php***

```php
<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateController extends Controller
{
    public function generateLink(Request $request)
    {
        $user = auth()->user();
        $product = Product::findOrFail($request->product_id);

        if (!$user->affiliate_code) {
            $user->generateAffiliateCode();
        }

        $affiliateLink = AffiliateLink::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'code' => $user->affiliate_code,
        ]);

        return response()->json([
            'link' => route('affiliate.redirect', ['code' => $user->affiliate_code, 'product' => $product->id]),
        ]);
    }

    public function redirectToCheckout($code, $productId)
    {
        $affiliateLink = AffiliateLink::where('code', $code)
            ->where('product_id', $productId)
            ->firstOrFail();

        $affiliateLink->increment('clicks');

        session(['affiliate_code' => $code]);

        return redirect()->route('checkout', $productId);
    }
}
```

***app/Http/Controllers/CheckoutController.php***

```php
<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\AffiliateLink;
use App\Models\User;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    public function show(Product $product)
    {
        $affiliateCode = session('affiliate_code');
        $discount = 0;

        if ($affiliateCode) {
            $affiliateUser = User::where('affiliate_code', $affiliateCode)->first();
            if ($affiliateUser) {
                $affiliateLink = AffiliateLink::where('user_id', $affiliateUser->id)
                    ->where('product_id', $product->id)
                    ->first();
                if ($affiliateLink) {
                    $discount = $product->price * ($product->affiliate_discount_percentage / 100);
                }
            }
        }

        return view('checkout', compact('product', 'discount'));
    }

    public function process(Request $request, Product $product)
    {
        $user = auth()->user();
        $affiliateCode = session('affiliate_code');
        $discount = 0;
        $commission = 0;
        $affiliateLinkId = null;

        if ($affiliateCode) {
            $affiliateUser = User::where('affiliate_code', $affiliateCode)->first();
            if ($affiliateUser) {
                $affiliateLink = AffiliateLink::where('user_id', $affiliateUser->id)
                    ->where('product_id', $product->id)
                    ->first();
                if ($affiliateLink) {
                    $discount = $product->price * ($product->affiliate_discount_percentage / 100);
                    $commission = $product->price * ($product->affiliate_commission_percentage / 100);
                    $affiliateLinkId = $affiliateLink->id;
                }
            }
        }

        $totalAmount = $product->price - $discount;

        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'affiliate_link_id' => $affiliateLinkId,
            'total_amount' => $totalAmount,
            'discount_amount' => $discount,
            'commission_amount' => $commission,
        ]);

        // Process payment here (integrate with your preferred payment gateway)

        return redirect()->route('order.confirmation', $order);
    }
}
```

### 6. Route Definition:

We defined routes to connect user actions to controller methods.

***routes/web.php***

```php
<?php

use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\CheckoutController;

Route::middleware(['auth'])->group(function () {
    Route::post('/affiliate/generate', [AffiliateController::class, 'generateLink'])->name('affiliate.generate');
    Route::get('/checkout/{product}', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout/{product}', [CheckoutController::class, 'process'])->name('checkout.process');
});

Route::get('/go/{code}', [AffiliateController::class, 'redirectToCheckout'])->name('affiliate.redirect');
```

### 7. Implementation Details:

a) Affiliate Link Generation:

- We added a `generateAffiliateCode()` method to the User model.
- In the AffiliateController, we ensure each user has a unique affiliate code.
- Affiliate links are generated using the user's affiliate code and product ID.

b) Link Redirection:

- When an affiliate link is clicked, it's recorded and the user is redirected to the checkout page.

c) Checkout Process:

- The system checks for an affiliate code in the session.
- If present, it calculates the buyer's discount and the affiliate's commission.
- An order is created with the calculated amounts.

### 8. Refinement:

Based on your feedback, we refined the system to make better use of the `affiliate_code`:

- Each user now has a single, persistent affiliate code for all their links.
- The code is generated only when a user first creates an affiliate link.
- We updated the controllers to use this affiliate code for lookups and attribution.

### 9. Final Solution:

The final solution includes:

- Migrations for creating necessary database tables
- Models (User, Product, AffiliateLink, Order) with relationships and methods
- Controllers (AffiliateController, CheckoutController) handling the core logic
- Routes for affiliate link generation, redirection, and checkout
- A system that allows for easy tracking of affiliate sales and commission calculation

This solution provides a flexible and efficient affiliate marketing system that:

- Allows users to generate unique affiliate links
- Tracks clicks on these links
- Applies discounts to buyers using affiliate links
- Calculates commissions for affiliates when a sale is made
- Maintains a record of all transactions for reporting and payout purposes

<p>The system is designed to be scalable and can be further enhanced with features like an affiliate dashboard, payout management, and detailed analytics.</p>

<p>To implement the 10 suggested improvements, we will make additions to the existing code structure. I'll guide you through each enhancement step by step, starting with the essential pieces needed for each improvement. For brevity, I'll summarize where code changes will happen and provide code snippets where necessary.</p>

---

### 1. **Affiliate Performance Dashboard**

Create a dashboard view and controller for affiliates to track clicks, commissions, and sales.

#### Controller: `AffiliateDashboardController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\AffiliateLink;
use App\Models\Order;
use Illuminate\Http\Request;

class AffiliateDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        // Get affiliate's performance
        $affiliateLinks = AffiliateLink::where('user_id', $user->id)->with('product')->get();
        $totalClicks = $affiliateLinks->sum('clicks');
        $totalCommissions = Order::where('affiliate_link_id', $affiliateLinks->pluck('id'))->sum('commission_amount');

        return view('affiliate.dashboard', compact('affiliateLinks', 'totalClicks', 'totalCommissions'));
    }
}
```

#### Route:

```php
Route::get('/affiliate/dashboard', [AffiliateDashboardController::class, 'index'])->name('affiliate.dashboard');
```

#### View: `resources/views/affiliate/dashboard.blade.php`

```php
@extends('layouts.app')

@section('content')
    <h1>Affiliate Dashboard</h1>
    <p>Total Clicks: {{ $totalClicks }}</p>
    <p>Total Commissions: ${{ $totalCommissions }}</p>

    <h2>Your Links</h2>
    <ul>
        @foreach ($affiliateLinks as $link)
            <li>{{ $link->product->name }} - Clicks: {{ $link->clicks }}</li>
        @endforeach
    </ul>
@endsection
```

---

### 2. **Payout System**

Create a `Payout` model and table for tracking payouts, and allow affiliates to request withdrawals.

#### Migration: `xxxx_xx_xx_create_payouts_table.php`

```php
Schema::create('payouts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('affiliate_id')->constrained('users');
    $table->decimal('amount', 10, 2);
    $table->string('status')->default('Pending');
    $table->timestamps();
});
```

#### Model: `Payout.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payout extends Model
{
    protected $fillable = ['affiliate_id', 'amount', 'status'];

    public function affiliate()
    {
        return $this->belongsTo(User::class, 'affiliate_id');
    }
}
```

#### Controller: `PayoutController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    public function requestPayout()
    {
        $user = auth()->user();
        $totalCommissions = $user->orders()->sum('commission_amount');

        if ($totalCommissions > 0) {
            Payout::create([
                'affiliate_id' => $user->id,
                'amount' => $totalCommissions,
            ]);

            return back()->with('success', 'Payout requested successfully.');
        }

        return back()->with('error', 'No commissions available for payout.');
    }
}
```

#### Route:

```php
Route::post('/affiliate/payout', [PayoutController::class, 'requestPayout'])->name('affiliate.payout');
```

---

### 3. **Referral Bonus System**

Add a referral bonus system by tracking when a user registers through an affiliate link.

#### Migration: Add `referred_by` to `users` table.

```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('referred_by')->nullable()->constrained('users');
});
```

#### Referral Bonus in AffiliateController:

```php
if ($request->has('referral_code')) {
    $referrer = User::where('affiliate_code', $request->referral_code)->first();
    if ($referrer) {
        $newUser->referred_by = $referrer->id;
        $newUser->save();

        // Award referrer a bonus
        $referrer->increment('referral_bonus', 10); // Example: $10 bonus
    }
}
```

---

### 4. **Coupon Integration**

Change discount handling from automatic to a coupon code system.

#### Add `coupon_code` to Products:

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('coupon_code')->nullable();
});
```

#### Update `CheckoutController.php`:

```php
if ($request->has('coupon_code')) {
    $coupon = $product->coupon_code;
    if ($coupon && $coupon === $request->coupon_code) {
        $discount = $product->price * ($product->affiliate_discount_percentage / 100);
    }
}
```

---

### 5. **Email Notifications**

Set up email notifications for affiliate-related events.

#### Notification: `CommissionEarnedNotification.php`

```php
<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

class CommissionEarnedNotification extends Notification
{
    private $amount;

    public function __construct($amount)
    {
        $this->amount = $amount;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Commission Earned')
            ->line('You have earned a new commission of $' . $this->amount);
    }
}
```

#### Trigger Email Notification:

In `CheckoutController`:

```php
if ($affiliateUser) {
    $affiliateUser->notify(new CommissionEarnedNotification($commission));
}
```

---

### 6. **Optimize Affiliate Code Storage**

Instead of generating a new affiliate code each time, ensure it's created once and persisted.

#### Modify `generateAffiliateCode` Method in User Model:

```php
public function generateAffiliateCode()
{
    if (!$this->affiliate_code) {
        do {
            $code = substr(md5(uniqid(mt_rand(), true)), 0, 10);
        } while (static::where('affiliate_code', $code)->exists());

        $this->affiliate_code = $code;
        $this->save();
    }
}
```

---

### 7. **Rate-Limiting on Clicks**

Prevent click fraud by adding rate-limiting.

#### Middleware: `RateLimitAffiliateClicks.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\RateLimiter;

class RateLimitAffiliateClicks
{
    public function handle($request, Closure $next)
    {
        $ip = $request->ip();
        $key = 'affiliate-clicks:' . $ip;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429, 'Too many clicks from your IP address.');
        }

        RateLimiter::hit($key, 60); // Limit: 5 clicks per minute

        return $next($request);
    }
}
```

#### Apply Middleware:

```php
Route::middleware(['ratelimit:affiliate.clicks'])->get('/go/{code}', [AffiliateController::class, 'redirectToCheckout']);
```

---

### 8. **Commission Splitting**

For commission splitting, modify the `Order` model and add logic to handle multiple affiliates.

#### Modify `orders` table migration:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('second_affiliate_link_id')->nullable()->constrained('affiliate_links');
});
```

#### Update Commission Logic:

In `CheckoutController`:

```php
$firstAffiliateCommission = $product->price * 0.05; // 5% to first affiliate
$secondAffiliateCommission = $product->price * 0.02; // 2% to second affiliate
```

---

### 9. **Advanced Analytics**

Integrate with Google Analytics for advanced tracking:

- Use Google Analytics tags or a library like `spatie/laravel-analytics` to track detailed data.

```bash
composer require spatie/laravel-analytics
```

---

### 10. **Multilingual and Multi-currency Support**

Install Laravel Localization:

```bash
composer require mcamara/laravel-localization
```

Configure `config/app.php` for multiple languages and use currency conversion libraries like `florianv/laravel-swap` for
currency exchange.
