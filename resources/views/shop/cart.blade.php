@extends('shop.layout')
@section('title', 'السلة')
@section('content')
<h1 class="font-kufi text-xl font-extrabold mb-4">سلة المشتريات</h1>
@if (!count($cart['items']))
  <div class="bg-white rounded-2xl border border-line p-10 text-center"><p class="mb-4">السلة فارغة.</p><a href="{{ route('shop.index') }}" class="inline-flex h-12 px-6 items-center rounded-xl bg-brand text-ink font-bold">تصفّح القطع</a></div>
@else
<div class="grid lg:grid-cols-3 gap-6">
  <div class="lg:col-span-2 space-y-3">
    @foreach ($cart['items'] as $i)
    <div class="bg-white rounded-2xl border border-line p-4 flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-[180px]"><a href="{{ route('shop.part', $i['part_id']) }}" class="font-semibold">{{ $i['name'] }}</a><div class="text-xs text-neutral-500" dir="ltr">{{ $i['sku'] }}</div>@unless ($i['in_stock'])<div class="text-sm text-red-800 font-semibold">الكمية المطلوبة غير متاحة (المتاح {{ $i['available'] }})</div>@endunless</div>
      <form method="post" action="{{ route('shop.cart.update', $i['id']) }}" class="flex items-center gap-2">@csrf @method('PATCH')
        <input type="number" name="qty" value="{{ $i['qty'] }}" min="0" max="99" aria-label="الكمية" class="w-20 h-11 rounded-xl border px-2 num"><button class="h-11 px-3 rounded-xl border text-sm">تحديث</button></form>
      <b class="num w-28 text-left">{{ number_format($i['line_total'], 2) }}</b>
      <form method="post" action="{{ route('shop.cart.update', $i['id']) }}">@csrf @method('PATCH')<input type="hidden" name="qty" value="0"><button class="h-11 px-3 text-red-800 text-sm font-semibold">حذف</button></form>
    </div>
    @endforeach
  </div>
  <aside class="bg-white rounded-2xl border border-line p-5 self-start space-y-2 text-sm">
    <div class="flex justify-between"><span>المنتجات</span><span class="num">{{ number_format($cart['totals']['subtotal'], 2) }}</span></div>
    <div class="flex justify-between"><span>الشحن</span><span class="num">{{ number_format($cart['totals']['shipping'], 2) }}</span></div>
    <div class="flex justify-between text-neutral-500"><span>منها ضريبة 15%</span><span class="num">{{ number_format($cart['totals']['vat'], 2) }}</span></div>
    <div class="flex justify-between text-lg font-bold border-t border-line pt-2"><span>الإجمالي</span><span class="num">{{ number_format($cart['totals']['total'], 2) }} ر.س</span></div>
    @if ($cart['has_issues'])<p class="text-red-800">عدّل الكميات غير المتاحة للمتابعة.</p>
    @else<a href="{{ route('shop.checkout') }}" class="mt-2 flex h-12 items-center justify-center rounded-xl bg-brand text-ink font-bold">إتمام الشراء</a>@endif
  </aside>
</div>
@endif
@endsection
