@extends('shop.layout')
@section('title', 'الطلب '.$order->number)
@section('content')
<div class="max-w-3xl mx-auto space-y-4">
  <div class="bg-white rounded-2xl border border-line p-5">
    <div class="flex flex-wrap items-center gap-3"><h1 class="font-kufi text-xl font-extrabold">طلب <span dir="ltr" class="num">{{ $order->number }}</span></h1><span class="px-3 py-1 rounded-full bg-ink text-white text-sm font-bold">{{ $order->status_label }}</span></div>
    <p class="text-sm text-neutral-600 mt-1">{{ $order->placed_at->format('Y-m-d H:i') }}</p>
  </div>
  @if ($order->payment_method === 'bank' && $order->status === 'pending_transfer')
  <div class="bg-white rounded-2xl border-2 border-brand p-5 space-y-3">
    <h2 class="font-bold">أكمل الدفع بالتحويل البنكي</h2>
    <dl class="text-sm grid grid-cols-[auto_1fr] gap-x-4 gap-y-1"><dt class="text-neutral-600">المبلغ</dt><dd class="num font-bold">{{ number_format($order->total, 2) }} ر.س</dd><dt class="text-neutral-600">المستفيد</dt><dd>{{ config('dp.seller_name') }}</dd><dt class="text-neutral-600">البنك / الآيبان</dt><dd dir="ltr" class="text-right">[اسم البنك] / [رقم الآيبان]</dd><dt class="text-neutral-600">مرجع التحويل</dt><dd dir="ltr" class="text-right font-mono">{{ $order->number }}</dd></dl>
    @if ($order->reject_reason)<p class="text-red-800 text-sm">تم رفض الإيصال السابق: {{ $order->reject_reason }}</p>@endif
    @if ($order->payment_status === 'pending')<p class="text-sm text-amber-800">تم رفع الإيصال وهو قيد المراجعة. يمكنك رفع إيصال آخر لاستبداله.</p>@endif
    <form method="post" action="{{ route('shop.order.receipt', $order->number) }}" enctype="multipart/form-data" class="flex flex-wrap gap-2 items-center">@csrf
      <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" required aria-label="إيصال التحويل" class="text-sm"><button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">رفع الإيصال</button></form>
  </div>
  @endif
  <div class="bg-white rounded-2xl border border-line p-5">
    <table class="w-full text-sm"><tbody>@foreach ($order->items as $i)<tr class="border-b border-line last:border-0"><td class="py-2">{{ $i->name }}</td><td class="num text-center">× {{ $i->qty }}</td><td class="num text-left">{{ number_format($i->line_total, 2) }}</td></tr>@endforeach</tbody></table>
    <div class="mt-3 text-sm space-y-1 max-w-xs mr-auto"><div class="flex justify-between"><span>الشحن</span><span class="num">{{ number_format($order->shipping, 2) }}</span></div><div class="flex justify-between font-bold text-base"><span>الإجمالي</span><span class="num">{{ number_format($order->total, 2) }} ر.س</span></div></div>
  </div>
  <div class="bg-white rounded-2xl border border-line p-5"><h2 class="font-bold mb-3">حالة الطلب</h2>
    <ol class="space-y-3 text-sm border-r-2 border-line pr-4">@foreach ($order->events as $e)<li class="relative"><span class="absolute -right-[22px] top-1.5 w-3 h-3 rounded-full bg-brand"></span><div class="font-semibold">{{ config('dp.order_statuses.'.$e->status, $e->status) }}</div><div class="text-neutral-600">{{ $e->note }}</div><div class="text-xs text-neutral-500 num">{{ $e->created_at->format('Y-m-d H:i') }}</div></li>@endforeach</ol></div>
  @if (in_array($order->status, ['new', 'pending_transfer']))
  <form method="post" action="{{ route('shop.order.cancel', $order->number) }}" onsubmit="return confirm('إلغاء الطلب؟')">@csrf<button class="h-11 px-5 rounded-xl border-2 border-red-700 text-red-800 font-semibold">إلغاء الطلب</button></form>
  @endif
</div>
@endsection
