<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Secure Checkout | {{ config('app.name') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://www.paypal.com/sdk/js?client-id={{ urlencode($clientId) }}&currency={{ urlencode($currency) }}&intent=capture"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100">
    <main class="mx-auto flex min-h-screen max-w-6xl items-center justify-center px-5 py-12">
        <div class="grid w-full overflow-hidden rounded-3xl border border-white/10 bg-slate-900 shadow-2xl md:grid-cols-2">
            <section class="p-8 md:p-12">
                <div class="mb-10 flex items-center gap-3">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-blue-500 text-xl font-black">P</div>
                    <div><p class="font-semibold tracking-wide">PAYMENT PORTAL</p><p class="text-xs text-slate-400">Secure sandbox checkout</p></div>
                </div>
                <p class="mb-3 text-sm font-semibold uppercase tracking-[.25em] text-blue-300">Demo purchase</p>
                <h1 class="mb-4 text-4xl font-bold tracking-tight">Complete your payment</h1>
                <p class="mb-8 max-w-md leading-7 text-slate-400">Pay securely using the PayPal Sandbox. No real money will be charged in this test environment.</p>
                <div class="rounded-2xl border border-white/10 bg-slate-800/70 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div><p class="font-semibold">{{ $productName }}</p><p class="mt-1 text-sm text-slate-400">One-time demo payment</p></div>
                        <span class="rounded-full bg-emerald-400/10 px-3 py-1 text-xs font-medium text-emerald-300">Test item</span>
                    </div>
                    <div class="my-5 border-t border-white/10"></div>
                    <div class="flex items-center justify-between"><span class="text-slate-400">Total due</span><strong class="text-3xl">{{ $currency }} {{ number_format((float)$amount, 2) }}</strong></div>
                </div>
                <div class="mt-8 flex items-center gap-2 text-xs text-slate-500"><span class="text-emerald-400">●</span> Encrypted connection · Sandbox mode</div>
            </section>
            <section class="flex flex-col justify-center bg-white p-8 text-slate-900 md:p-12">
                <div class="mx-auto w-full max-w-sm">
                    <div class="mb-7 text-center"><h2 class="text-2xl font-bold">Pay with PayPal</h2><p class="mt-2 text-sm text-slate-500">Continue with your test buyer account</p></div>
                    @if(!$clientId)
                        <div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">PayPal Client ID is missing. Add PAYPAL_CLIENT_ID to your .env and clear config cache.</div>
                    @else
                        <div id="paypal-button-container"></div>
                    @endif
                    <div id="payment-message" class="mt-5 hidden rounded-xl p-4 text-sm" role="status" aria-live="polite"></div>
                    <div id="payment-result" class="mt-5 hidden rounded-2xl border border-emerald-200 bg-emerald-50 p-5 text-emerald-900">
                        <div class="mb-2 text-2xl">✓</div><h3 class="text-lg font-bold">Payment successful</h3>
                        <p class="mt-1 text-sm">PayPal has confirmed your payment.</p>
                        <dl class="mt-4 space-y-2 break-all text-xs"><div><dt class="font-semibold">Order ID</dt><dd id="result-order"></dd></div><div><dt class="font-semibold">Capture ID</dt><dd id="result-capture"></dd></div><div><dt class="font-semibold">Payer</dt><dd id="result-email"></dd></div></dl>
                    </div>
                    <p class="mt-8 text-center text-xs leading-5 text-slate-400">This is a demonstration checkout. Do not enter real payment credentials. Use only your PayPal Sandbox buyer account.</p>
                </div>
            </section>
        </div>
    </main>
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const messageBox = document.getElementById('payment-message');

function showMessage(message, type = 'error') {
    messageBox.textContent = message;
    messageBox.className = 'mt-5 rounded-xl p-4 text-sm ' + (type === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-700');
}
function hideMessage() { messageBox.classList.add('hidden'); }

if (window.paypal) {
    paypal.Buttons({
        style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'paypal', height: 48 },
        createOrder: async () => {
            hideMessage();
            const response = await fetch(@json(route('paypal.create-order')), {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify({})
            });
            const data = await response.json();
            if (!response.ok || !data.id) throw new Error(data.message || 'Could not start PayPal checkout.');
            return data.id;
        },
        onApprove: async (data) => {
            showMessage('Payment approved. Verifying and capturing with PayPal…', 'success');
            try {
                const response = await fetch(@json(route('paypal.capture-order')), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ orderID: data.orderID })
                });
                const result = await response.json();
                if (!response.ok || result.status !== 'COMPLETED') throw new Error(result.message || 'Payment could not be verified.');
                messageBox.classList.add('hidden');
                document.getElementById('payment-result').classList.remove('hidden');
                document.getElementById('result-order').textContent = result.orderID || '';
                document.getElementById('result-capture').textContent = result.captureID || '';
                document.getElementById('result-email').textContent = result.payerEmail || 'Not provided';
            } catch (error) {
                showMessage(error.message || 'Payment verification failed.');
            }
        },
        onCancel: () => showMessage('You cancelled the PayPal checkout. No payment was captured.'),
        onError: (error) => showMessage(error.message || 'PayPal checkout encountered an error.')
    }).render('#paypal-button-container');
}
</script>
</body>
</html>
