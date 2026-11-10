@extends('shop.layout')
@section('title', 'إتمام الشراء')
@section('content')
@php
  $deliveryTotal = $cart['totals']['total'];
  $pickupTotal = $pickupTotals['total'];
@endphp
<h1 class="font-kufi text-xl font-extrabold mb-4">إتمام الشراء</h1>
<form method="post" action="{{ route('shop.checkout.place') }}" class="grid lg:grid-cols-3 gap-6"
      x-data="{ mode: '{{ old('fulfillment', 'delivery') }}', branch: '{{ $selectedBranch }}' }">@csrf
  <div class="lg:col-span-2 space-y-4">

    {{-- 1. طريقة الاستلام --}}
    <section class="bg-white rounded-2xl border border-line p-5">
      <h2 class="font-bold mb-3">كيف تريد استلام طلبك؟</h2>
      <div class="grid sm:grid-cols-2 gap-3">
        <label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3" :class="mode === 'delivery' ? 'border-ink bg-paper' : 'border-line'">
          <input type="radio" name="fulfillment" value="delivery" x-model="mode" class="w-5 h-5 accent-black mt-0.5">
          <span><b class="block">توصيل لعنوانك</b><span class="text-sm text-neutral-600">رسوم الشحن <span class="num">{{ number_format($cart['totals']['shipping'], 2) }}</span> ر.س</span></span>
        </label>
        <label class="cursor-pointer rounded-xl border-2 p-4 flex gap-3" :class="mode === 'pickup' ? 'border-ink bg-paper' : 'border-line'">
          <input type="radio" name="fulfillment" value="pickup" x-model="mode" class="w-5 h-5 accent-black mt-0.5">
          <span><b class="block">استلام من الفرع</b><span class="text-sm text-emerald-700 font-semibold">بدون رسوم شحن</span></span>
        </label>
      </div>
    </section>

    {{-- 2. الفرع --}}
    <section class="bg-white rounded-2xl border border-line p-5">
      <h2 class="font-bold mb-1" x-text="mode === 'pickup' ? 'اختر الفرع الذي ستستلم منه' : 'اختر الفرع الذي سيشحن طلبك'">اختر الفرع</h2>
      <p class="text-sm text-neutral-600 mb-3">نعرض توفر قطع سلتك في كل فرع.</p>
      <div class="space-y-2">
        @foreach ($branches as $b)
          @php $ok = $b->missing->isEmpty(); @endphp
          <label class="block rounded-xl border-2 p-4 {{ $ok ? 'cursor-pointer' : 'opacity-60' }}"
                 :class="branch == '{{ $b->id }}' ? 'border-ink bg-paper' : 'border-line'"
                 x-show="mode === 'pickup' ? {{ $b->pickup_enabled ? 'true' : 'false' }} : {{ $b->delivery_enabled ? 'true' : 'false' }}">
            <span class="flex gap-3">
              <input type="radio" name="branch_id" value="{{ $b->id }}" x-model="branch" class="w-5 h-5 accent-black mt-0.5" @disabled(!$ok)>
              <span class="flex-1">
                <span class="flex flex-wrap items-center gap-2"><b>{{ $b->name }}</b><span class="text-sm text-neutral-500">{{ $b->city }}</span>
                  @if ($ok)<span class="text-xs rounded-full bg-emerald-100 text-emerald-900 px-2 py-0.5">كل القطع متوفرة</span>
                  @else<span class="text-xs rounded-full bg-red-100 text-red-800 px-2 py-0.5">غير متوفر: {{ $b->missing->implode('، ') }}</span>@endif
                </span>
                @if ($b->address)<span class="block text-sm text-neutral-600 mt-1">{{ $b->address }}</span>@endif
                @if ($b->hours)<span class="block text-xs text-neutral-500 mt-0.5">{{ $b->hours }}@if ($b->phone) · <span dir="ltr">{{ $b->phone }}</span>@endif</span>@endif
              </span>
            </span>
          </label>
        @endforeach
      </div>
    </section>

    {{-- 3. البيانات --}}
    <section class="bg-white rounded-2xl border border-line p-5 grid sm:grid-cols-2 gap-3">
      <h2 class="font-bold sm:col-span-2" x-text="mode === 'pickup' ? 'بيانات المستلم' : 'بيانات التوصيل'">بيانات التوصيل</h2>
      <label class="text-sm">الاسم<input name="name" value="{{ old('name') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <label class="text-sm">الجوال<input name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr" placeholder="05xxxxxxxx" class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <template x-if="mode === 'delivery'">
        <div class="contents">
          <label class="text-sm">المدينة<input name="city" value="{{ old('city') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
          <label class="text-sm">العنوان الوطني (اختياري)<input name="national_address" value="{{ old('national_address') }}" dir="ltr" maxlength="8" placeholder="RNAA4021" class="mt-1 w-full h-11 rounded-xl border px-3"></label>
          <label class="text-sm sm:col-span-2">العنوان التفصيلي<input name="line" value="{{ old('line') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
        </div>
      </template>
      <p x-show="mode === 'pickup'" x-cloak class="sm:col-span-2 text-sm text-neutral-600">نرسل لك رسالة عندما يكون الطلب جاهزاً. أحضر رقم الطلب عند الاستلام.</p>
    </section>

    <section class="bg-white rounded-2xl border border-line p-5 space-y-2">
      <h2 class="font-bold">طريقة الدفع</h2>
      <label class="flex items-center gap-3 min-h-[48px] rounded-xl border px-3"><input type="radio" name="payment_method" value="cod" class="w-5 h-5 accent-black" @checked(old('payment_method', 'cod') === 'cod')><span x-text="mode === 'pickup' ? 'الدفع في الفرع عند الاستلام' : 'الدفع عند الاستلام'">الدفع عند الاستلام</span></label>
      <label class="flex items-center gap-3 min-h-[48px] rounded-xl border px-3"><input type="radio" name="payment_method" value="bank" class="w-5 h-5 accent-black" @checked(old('payment_method') === 'bank')>تحويل بنكي (ترفع الإيصال بعد الطلب)</label>
    </section>
  </div>

  <aside class="bg-white rounded-2xl border border-line p-5 self-start space-y-2 text-sm lg:sticky lg:top-32">
    @foreach ($cart['items'] as $i)<div class="flex justify-between gap-2"><span>{{ $i['name'] }} × <span class="num">{{ $i['qty'] }}</span></span><span class="num">{{ number_format($i['line_total'], 2) }}</span></div>@endforeach
    <div class="flex justify-between border-t border-line pt-2"><span>الشحن</span>
      <span class="num" x-text="mode === 'pickup' ? 'مجاناً' : '{{ number_format($cart['totals']['shipping'], 2) }}'">{{ number_format($cart['totals']['shipping'], 2) }}</span></div>
    <div class="flex justify-between text-lg font-bold"><span>الإجمالي</span>
      <span class="num"><span x-text="mode === 'pickup' ? '{{ number_format($pickupTotal, 2) }}' : '{{ number_format($deliveryTotal, 2) }}'">{{ number_format($deliveryTotal, 2) }}</span> ر.س</span></div>
    <button :disabled="!branch" class="mt-2 w-full h-12 rounded-xl bg-brand text-ink font-bold disabled:opacity-40">تأكيد الطلب</button>
    <p x-show="!branch" class="text-xs text-red-800">اختر الفرع أولاً</p>
  </aside>
</form>
@endsection
