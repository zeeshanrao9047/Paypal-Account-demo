<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><script src="https://cdn.tailwindcss.com"></script><title>Payment response</title></head>
<body class="grid min-h-screen place-items-center bg-slate-950 p-6 text-white">
<div class="max-w-md rounded-2xl border border-white/10 bg-slate-900 p-8 text-center">
@if($result === 'success')
<div class="text-4xl text-emerald-400">✓</div><h1 class="mt-4 text-2xl font-bold">Returned from PayPal</h1><p class="mt-3 text-slate-400">The browser returned from PayPal. The server-side capture response on the checkout page is the source of truth for payment completion.</p>
@else
<div class="text-4xl text-amber-400">!</div><h1 class="mt-4 text-2xl font-bold">Checkout cancelled</h1><p class="mt-3 text-slate-400">You cancelled or left the PayPal checkout. No successful capture has been confirmed.</p>
@endif
<a href="{{ route('payments.index') }}" class="mt-7 inline-block rounded-lg bg-blue-500 px-5 py-3 font-semibold text-white">Back to checkout</a>
</div></body></html>
