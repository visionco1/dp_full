@extends('shop.layout')
@section('title', 'إتمام الشراء')
@section('content')
<h1 class="font-kufi text-xl font-extrabold mb-4">إتمام الشراء</h1>
<form method="post" action="{{ route('shop.checkout.place') }}" class="grid lg:grid-cols-3 gap-6">@csrf
  <div class="lg:col-span-2 space-y-4">
    <section class="bg-white rounded-2xl border border-line p-5 grid sm:grid-cols-2 gap-3">
      <h2 class="font-bold sm:col-span-2">بيانات التوصيل</h2>
      <label class="text-sm">الاسم<input name="name" value="{{ old('name') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <label class="text-sm">الجوال<input name="phone" value="{{ old('phone') }}" required inputmode="tel" dir="ltr" placeholder="05xxxxxxxx" class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <label class="text-sm">المدينة<input name="city" value="{{ old('city') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <label class="text-sm">العنوان الوطني (اختياري)<input name="national_address" value="{{ old('national_address') }}" dir="ltr" maxlength="8" placeholder="RNAA4021" class="mt-1 w-full h-11 rounded-xl border px-3"></label>
      <label class="text-sm sm:col-span-2">العنوان التفصيلي<input name="line" value="{{ old('line') }}" required class="mt-1 w-full h-11 rounded-xl border px-3"></label>
    </section>
    <section class="bg-white rounded-2xl border border-line p-5 space-y-2">
      <h2 class="font-bold">طريقة الدفع</h2>
      <label class="flex items-center gap-3 min-h-[48px] rounded-xl border px-3"><input type="radio" name="payment_method" value="cod" class="w-5 h-5 accent-black" @checked(old('payment_method', 'cod') === 'cod')>الدفع عند الاستلام</label>
      <label class="flex items-center gap-3 min-h-[48px] rounded-xl border px-3"><input type="radio" name="payment_method" value="bank" class="w-5 h-5 accent-black" @checked(old('payment_method') === 'bank')>تحويل بنكي (ترفع الإيصال بعد الطلب)</label>
    </section>
  </div>
  <aside class="bg-white rounded-2xl border border-line p-5 self-start space-y-2 text-sm">
    @foreach ($cart['items'] as $i)<div class="flex justify-between gap-2"><span>{{ $i['name'] }} × <span class="num">{{ $i['qty'] }}</span></span><span class="num">{{ number_format($i['line_total'], 2) }}</span></div>@endforeach
    <div class="flex justify-between border-t border-line pt-2"><span>الشحن</span><span class="num">{{ number_format($cart['totals']['shipping'], 2) }}</span></div>
    <div class="flex justify-between text-lg font-bold"><span>الإجمالي</span><span class="num">{{ number_format($cart['totals']['total'], 2) }} ر.س</span></div>
    <button class="mt-2 w-full h-12 rounded-xl bg-brand text-ink font-bold">تأكيد الطلب</button>
  </aside>
</form>
@endsection
