@extends('layouts.admin')
@section('title', $order->number)
@section('heading', 'الطلب '.$order->number)
@section('actions')
  @can('orders.print')
  <a target="_blank" href="{{ route('admin.orders.print', ['type' => 'waybill', 'ids' => [$order->id]]) }}" class="h-11 px-4 rounded-xl border bg-white inline-flex items-center text-sm font-semibold">بوليصة الشحن</a>
  <a target="_blank" href="{{ route('admin.orders.print', ['type' => 'invoice', 'ids' => [$order->id]]) }}" class="h-11 px-4 rounded-xl border bg-white inline-flex items-center text-sm font-semibold">الفاتورة الضريبية</a>
  @endcan
@endsection
@section('content')
@php $unmask = auth()->user()->hasPermission('reports.customers'); @endphp
<div class="grid gap-4 xl:grid-cols-3">
  <div class="xl:col-span-2 space-y-4">
    <section class="bg-white rounded-2xl border border-line p-5">
      <div class="flex flex-wrap items-center gap-3 mb-4">
        <span class="px-3 py-1 rounded-full bg-ink text-white text-sm font-bold">{{ $order->status_label }}</span>
        <span class="text-sm text-neutral-600">{{ $order->placed_at->format('Y-m-d H:i') }} · {{ ['app' => 'التطبيق', 'web' => 'الموقع', 'pos' => 'المعرض'][$order->channel] }}</span>
        @if ($order->waybill_no)<span class="text-sm">بوليصة: <b class="font-mono" dir="ltr">{{ $order->waybill_no }}</b></span>@endif
      </div>
      <table class="w-full text-sm"><thead class="text-xs text-neutral-500"><tr><th class="text-right py-1">القطعة</th><th>الكمية</th><th>السعر</th><th>الإجمالي</th></tr></thead><tbody>
      @foreach ($order->items as $i)<tr class="border-t border-line"><td class="py-2"><span class="font-mono text-xs" dir="ltr">{{ $i->sku }}</span><br>{{ $i->name }}</td><td class="text-center num">{{ $i->qty }}</td><td class="text-center num">{{ number_format($i->unit_price, 2) }}</td><td class="text-center num font-semibold">{{ number_format($i->line_total, 2) }}</td></tr>@endforeach
      </tbody></table>
      <dl class="mt-4 ml-auto max-w-xs space-y-1 text-sm">
        <div class="flex justify-between"><dt class="text-neutral-600">صافي قبل الضريبة</dt><dd class="num">{{ number_format($order->total - $order->vat, 2) }}</dd></div>
        <div class="flex justify-between"><dt class="text-neutral-600">ضريبة القيمة المضافة (15%)</dt><dd class="num">{{ number_format($order->vat, 2) }}</dd></div>
        <div class="flex justify-between"><dt class="text-neutral-600">الشحن (مضمّن)</dt><dd class="num">{{ number_format($order->shipping, 2) }}</dd></div>
        <div class="flex justify-between text-base font-bold border-t border-line pt-2"><dt>الإجمالي</dt><dd class="num">{{ number_format($order->total, 2) }} ر.س</dd></div>
      </dl>
    </section>

    @if ($order->payment_method === 'bank' && $order->status === 'pending_transfer')
    @can('orders.verify')
    <section id="verify" class="bg-white rounded-2xl border-2 border-brand p-5" x-data="{ checks: [false, false, false], rot: 0, zoom: 1, reject: false }">
      <h2 class="font-bold mb-3">التحقق من التحويل البنكي</h2>
      @if ($order->receipt_path)
        <div class="mb-3 flex items-center gap-2"><button type="button" @click="zoom=Math.min(3,zoom+.5)" class="h-9 px-3 rounded-lg border text-sm">تكبير +</button><button type="button" @click="zoom=Math.max(.5,zoom-.5)" class="h-9 px-3 rounded-lg border text-sm">تصغير −</button><button type="button" @click="rot=(rot+90)%360" class="h-9 px-3 rounded-lg border text-sm">تدوير</button><a class="text-sm text-brandDark font-semibold mr-auto" target="_blank" href="{{ asset('storage/'.$order->receipt_path) }}">فتح الأصل</a></div>
        <div class="overflow-auto max-h-96 rounded-xl bg-neutral-100 p-2">
          @if (str_ends_with($order->receipt_path, '.pdf'))<iframe src="{{ asset('storage/'.$order->receipt_path) }}" class="w-full h-96" title="إيصال التحويل"></iframe>
          @else<img src="{{ asset('storage/'.$order->receipt_path) }}" alt="إيصال التحويل" :style="`transform: rotate(${rot}deg) scale(${zoom}); transform-origin: center; transition: transform .2s`" class="mx-auto max-w-full">@endif
        </div>
      @else
        <p class="text-sm text-amber-800 bg-amber-50 rounded-xl p-3">لم يرفع العميل الإيصال بعد.</p>
      @endif
      <fieldset class="mt-4 space-y-1" @if(!$order->receipt_path) disabled @endif>
        <legend class="text-sm font-semibold mb-1">تأكد من الآتي قبل الاعتماد</legend>
        @foreach (['المبلغ المحوّل = '.number_format($order->total, 2).' ر.س', 'اسم المحوِّل/المرجع مطابق للطلب '.$order->number, 'التحويل ظاهر في كشف الحساب'] as $i => $l)
          <label class="flex items-center gap-2 min-h-[40px] text-sm"><input type="checkbox" x-model="checks[{{ $i }}]" class="w-5 h-5 accent-black">{{ $l }}</label>
        @endforeach
      </fieldset>
      <div class="mt-3 flex gap-2 flex-wrap">
        <form method="post" action="{{ route('admin.orders.approve', $order) }}">@csrf<button :disabled="!checks.every(Boolean)" class="h-12 px-6 rounded-xl bg-emerald-700 text-white font-bold disabled:opacity-40">اعتماد الدفع</button></form>
        <button type="button" @click="reject=!reject" class="h-12 px-6 rounded-xl border-2 border-red-700 text-red-800 font-bold">رفض الدفع</button>
      </div>
      <form x-show="reject" x-cloak method="post" action="{{ route('admin.orders.reject', $order) }}" class="mt-3 space-y-2">@csrf
        <select name="reason" class="w-full h-11 rounded-xl border px-3" required>
          <option value="">سبب الرفض…</option><option>الإيصال غير واضح</option><option>المبلغ غير مطابق</option><option>التحويل غير ظاهر في الحساب</option><option>الإيصال مكرر أو قديم</option>
        </select>
        <label class="flex items-center gap-2 text-sm min-h-[40px]"><input type="checkbox" name="cancel" value="1" class="w-5 h-5 accent-black">إلغاء الطلب نهائياً (وإرجاع القطع للمخزون)</label>
        <p class="text-xs text-neutral-600">سيصل العميل رسالة نصية وإشعار بالسبب، ويمكنه رفع إيصال جديد ما لم تُلغِ الطلب.</p>
        <button class="h-11 px-5 rounded-xl bg-red-700 text-white font-bold">تأكيد الرفض</button>
      </form>
    </section>
    @endcan
    @endif

    @can('orders.manage')
    @if (!in_array($order->status, ['completed', 'cancelled']))
    <section class="bg-white rounded-2xl border border-line p-5 space-y-3">
      <h2 class="font-bold">إجراءات</h2>
      <div class="flex flex-wrap gap-2 items-end">
        @if ($order->status === 'new')<form method="post" action="{{ route('admin.orders.transition', [$order, 'processing']) }}">@csrf<button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">بدء التجهيز</button></form>@endif
        @if ($order->status === 'processing')
          <form method="post" action="{{ route('admin.orders.transition', [$order, 'ship']) }}" class="flex gap-2 items-end">@csrf
            <label class="text-xs">رقم البوليصة (اختياري)<input name="waybill_no" dir="ltr" class="block h-11 rounded-xl border px-3 font-mono"></label>
            <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">تم الشحن</button></form>
        @endif
        @if ($order->status === 'shipped')<form method="post" action="{{ route('admin.orders.transition', [$order, 'complete']) }}">@csrf<button class="h-11 px-5 rounded-xl bg-emerald-700 text-white font-semibold">تم التسليم</button></form>@endif
        @if (in_array($order->status, ['new', 'pending_transfer', 'processing']))
          <form method="post" action="{{ route('admin.orders.transition', [$order, 'cancel']) }}" class="flex gap-2 items-end" onsubmit="return confirm('إلغاء الطلب وإرجاع الكميات للمخزون؟')">@csrf
            <label class="text-xs">سبب الإلغاء<input name="reason" required class="block h-11 rounded-xl border px-3"></label>
            <button class="h-11 px-5 rounded-xl border-2 border-red-700 text-red-800 font-semibold">إلغاء الطلب</button></form>
        @endif
      </div>
    </section>
    @endif
    @endcan
  </div>

  <aside class="space-y-4 self-start">
    <section class="bg-white rounded-2xl border border-line p-5 text-sm space-y-1">
      <h2 class="font-bold mb-2">العميل</h2>
      <div>{{ $order->customer_name }}</div>
      <div class="font-mono" dir="ltr">{{ $unmask ? $order->customer_phone : \App\Support\Phone::mask($order->customer_phone) }}</div>
      @if ($a = $order->address)<div class="text-neutral-600 pt-2">{{ $a['city'] ?? '' }} — {{ $a['line'] ?? '' }}@if(!empty($a['national_address'])) <span class="font-mono" dir="ltr">({{ $a['national_address'] }})</span>@endif</div>@endif
      <div class="pt-2 text-neutral-600">الدفع: {{ ['cod' => 'عند الاستلام', 'bank' => 'تحويل بنكي', 'cash' => 'نقداً', 'card' => 'بطاقة'][$order->payment_method] }} — {{ ['unpaid' => 'غير مدفوع', 'pending' => 'بانتظار التحقق', 'paid' => 'مدفوع', 'rejected' => 'مرفوض'][$order->payment_status] }}</div>
      @if ($order->reject_reason)<div class="text-red-800">سبب الرفض: {{ $order->reject_reason }}</div>@endif
      @if ($order->creator)<div class="text-neutral-600">سجّله: {{ $order->creator->name }}</div>@endif
    </section>
    <section class="bg-white rounded-2xl border border-line p-5">
      <h2 class="font-bold mb-3">السجل</h2>
      <ol class="space-y-3 text-sm border-r-2 border-line pr-4">
        @foreach ($order->events as $e)
          <li class="relative"><span class="absolute -right-[22px] top-1.5 w-3 h-3 rounded-full bg-brand"></span><div class="font-semibold">{{ config('dp.order_statuses.'.$e->status, $e->status) }}</div><div class="text-neutral-600">{{ $e->note }}</div><div class="text-xs text-neutral-500">{{ $e->created_at->format('Y-m-d H:i') }}@if($e->user) · {{ $e->user->name }}@endif</div></li>
        @endforeach
      </ol>
    </section>
  </aside>
</div>
@endsection
