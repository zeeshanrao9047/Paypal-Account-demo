# Laravel PayPal Sandbox Checkout

A small Laravel Blade application demonstrating a secure PayPal Checkout flow:
1. The browser renders the PayPal button using the PayPal JavaScript SDK.
2. Laravel creates the order server-side using PayPal Orders API v2.
3. The buyer approves the order in PayPal Sandbox.
4. Laravel captures the order server-side and stores the result.
5. The UI displays success, cancellation, or an error response.

> Sandbox only. Do not use sandbox credentials in production.

## Requirements
- PHP 8.2+
- Composer
- Laravel 11 or 12
- MySQL or SQLite
- PayPal Developer account

## 1. Create a Laravel project
```bash
composer create-project laravel/laravel paypal-demo
cd paypal-demo
```

Copy the folders/files from this package into the Laravel project, preserving their paths.

## 2. Configure environment
Add these lines to `.env`:
```env
PAYPAL_MODE=sandbox
PAYPAL_CLIENT_ID=your_sandbox_client_id
PAYPAL_CLIENT_SECRET=your_sandbox_secret
PAYPAL_CURRENCY=USD
PAYPAL_PRODUCT_NAME=Premium Demo Subscription
PAYPAL_PRODUCT_AMOUNT=49.00
```
Never commit `.env` or expose `PAYPAL_CLIENT_SECRET` in frontend JavaScript.

## 3. Configure database
Set your DB credentials in `.env`. For a quick local SQLite setup:
```bash
php artisan make:database
```
If that command is unavailable, create `database/database.sqlite` manually, then set:
```env
DB_CONNECTION=sqlite
```
For MySQL, use your normal database settings.

## 4. Run migrations and app
```bash
php artisan migrate
php artisan serve
```
Open http://127.0.0.1:8000/payments

## 5. Create PayPal Sandbox accounts and app
1. Visit https://developer.paypal.com/dashboard/
2. Sign in or create a PayPal Developer account.
3. Open **Apps & Credentials** and select **Sandbox**.
4. Create an app (or use the default app).
5. Copy its **Client ID** and **Secret** into `.env`.
6. Open **Sandbox > Accounts**. Use a **Personal** account as the buyer and a **Business** account as the merchant.
7. The app's credentials must belong to the sandbox business account.

Sandbox buyer login:
- When the PayPal popup/redirect asks for a PayPal login, use the Sandbox Personal account email and password shown under Sandbox > Accounts.
- Do not use your real PayPal account or real card.
- If the buyer account has no visible password, open the account details in the Developer Dashboard and view/reset its credentials.

## 6. Test the payment
1. Visit `/payments`.
2. Click the PayPal button.
3. Sign in with the Sandbox Personal buyer.
4. Approve the USD 49.00 payment.
5. Laravel captures the order and shows the capture response.
6. Check the `payments` table for the transaction record.

## Security notes
- The amount and product name are read from server configuration, not trusted from the browser.
- Client Secret is used only by Laravel.
- The capture endpoint calls PayPal from the server and checks that the capture status is `COMPLETED`.
- Add PayPal webhook signature verification before using this as a production payment system.
- Add idempotency, reconciliation, order ownership checks, logging policy, and stronger business validation before production.
- PayPal Sandbox and Live credentials/base URLs are separate.

## API flow
- `POST /paypal/create-order` -> Laravel obtains OAuth token and calls `POST /v2/checkout/orders`.
- PayPal JS SDK opens buyer approval.
- `POST /paypal/capture-order` -> Laravel calls `POST /v2/checkout/orders/{id}/capture`.
- `GET /payments/success` -> displays the server-confirmed result.

Official documentation:
- Orders API: https://developer.paypal.com/api/orders/v2
- Orders integration: https://developer.paypal.com/api/rest/integration/orders-api
- REST credentials: https://developer.paypal.com/api/rest
