@extends('layouts.admin')
@section('title', 'الطلبات')
@section('heading', 'إدارة الطلبات')
@section('content')
@php $tabs = ['' => ['الكل', $total]] + collect(config('dp.order_statuses'))->map(fn ($l, $k) => [$l, $counts[$k] ?? 0])->all(); @endphp
<div class="flex gap-2 overflow-x-auto pb-2 mb-4" role="tablist" aria-label="حالة الطلب">
  @foreach ($tabs as $k => [$label, $n])
    <a role="tab" aria-selected="{{ (string) request('status', '') === (string) $k ? 'true' : 'false' }}" href="{{ route('admin.orders.index', array_filter(['status' => $k, 'channel' => request('channel'), 'q' => request('q'), 'branch' => request('branch'), 'fulfillment' => request('fulfillment')])) }}"
       class="shrink-0 h-11 px-4 rounded-xl inline-flex items-center gap-2 text-sm font-semibold {{ (string) request('status', '') === (string) $k ? 'bg-ink text-white' : 'bg-white border border-line' }}">{{ $label }} <span class="num text-xs opacity-80">{{ $n }}</span></a>
  @endforeach
</div>

<form method="get" class="flex flex-wrap gap-2 mb-4">
  <input type="hidden" name="status" value="{{ request('status') }}">
  <input name="q" value="{{ request('q') }}" placeholder="رقم الطلب أو الجوال أو الاسم" class="h-11 rounded-xl border px-4 flex-1 min-w-[200px]">
  @if ($branches->count() > 1)<select name="branch" class="h-11 rounded-xl border px-3" aria-label="الفرع"><option value="">كل الفروع</option>@foreach ($branches as $b)<option value="{{ $b->id }}" @selected(request('branch') == $b->id)>{{ $b->name }}</option>@endforeach</select>@endif
  <select name="fulfillment" class="h-11 rounded-xl border px-3" aria-label="طريقة الاستلام"><option value="">توصيل واستلام</option><option value="delivery" @selected(request('fulfillment') === 'delivery')>توصيل</option><option value="pickup" @selected(request('fulfillment') === 'pickup')>استلام من الفرع</option></select>
  <select name="channel" class="h-11 rounded-xl border px-3"><option value="">كل القنوات</option><option value="app" @selected(request('channel') === 'app')>التطبيق</option><option value="web" @selected(request('channel') === 'web')>الموقع</option><option value="pos" @selected(request('channel') === 'pos')>المعرض</option></select>
  <button class="h-11 px-5 rounded-xl bg-ink text-white font-semibold">بحث</button>
</form>

<form method="get" action="{{ route('admin.orders.print') }}" target="_blank" x-data="{ n: 0, all: false, recount() { this.n = this.$root.querySelectorAll('input[name=\'ids[]\']:checked').length } }">
  @can('orders.print')
  <div x-show="n>0" x-cloak class="sticky top-2 z-10 mb-3 rounded-xl bg-ink text-white px-4 py-3 flex flex-wrap items-center gap-3">
    <span class="text-sm"><b class="num" x-text="n"></b> طلب محدد</span>
    <button name="type" value="waybill" class="h-10 px-4 rounded-lg bg-brand text-ink font-bold text-sm">طباعة بوالص الشحن</button>
    <button name="type" value="invoice" class="h-10 px-4 rounded-lg bg-white text-ink font-bold text-sm">طباعة الفواتير الضريبية</button>
  </div>
  @endcan
  <div class="bg-white rounded-2xl border border-line overflow-x-auto">
    <table class="w-full text-sm min-w-[820px]">
      <thead class="bg-paper text-xs text-neutral-600"><tr>
        @can('orders.print')<th class="p-3 w-10"><input type="checkbox" aria-label="تحديد الكل" class="w-5 h-5 accent-black" @change="all=$event.target.checked; $root.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=all); recount()"></th>@endcan
        <th class="p-3 text-right">رقم الطلب</th><th class="p-3 text-right">العميل</th><th class="p-3">القناة</th><th class="p-3">الفرع / الاستلام</th><th class="p-3">الحالة</th><th class="p-3">الدفع</th><th class="p-3">الإجمالي</th><th class="p-3"></th></tr></thead>
      <tbody>
      @forelse ($orders as $o)
        @php $pill = ['new' => 'bg-neutral-200 text-neutral-900', 'pending_transfer' => 'bg-orange-100 text-orange-900', 'processing' => 'bg-blue-100 text-blue-900', 'ready' => 'bg-violet-100 text-violet-900', 'shipped' => 'bg-ink text-white', 'completed' => 'bg-emerald-100 text-emerald-900', 'cancelled' => 'bg-red-100 text-red-900'][$o->status]; @endphp
        <tr class="border-t border-line">
          @can('orders.print')<td class="p-3"><input type="checkbox" name="ids[]" value="{{ $o->id }}" aria-label="تحديد {{ $o->number }}" class="w-5 h-5 accent-black" @change="recount()"></td>@endcan
          <td class="p-3"><a href="{{ route('admin.orders.show', $o) }}" class="font-mono font-semibold" dir="ltr">{{ $o->number }}</a><div class="text-xs text-neutral-500">{{ $o->placed_at->format('Y-m-d H:i') }}</div></td>
          <td class="p-3">{{ $o->customer_name }}<div class="text-xs text-neutral-500 font-mono" dir="ltr">{{ $unmask ? $o->customer_phone : \App\Support\Phone::mask($o->customer_phone) }}</div></td>
          <td class="p-3 text-center">{{ ['app' => 'التطبيق', 'web' => 'الموقع', 'pos' => 'المعرض'][$o->channel] }}</td>
          <td class="p-3 text-center text-xs">{{ $o->branch?->name }}<div class="{{ $o->fulfillment === 'pickup' ? 'text-violet-800 font-semibold' : 'text-neutral-500' }}">{{ \App\Models\Order::FULFILLMENT[$o->fulfillment] ?? '' }}</div></td>
          <td class="p-3 text-center"><span class="px-2.5 py-1 rounded-full text-xs font-bold {{ $pill }}">{{ $o->status_label }}</span></td>
          <td class="p-3 text-center text-xs">{{ ['cod' => 'عند الاستلام', 'bank' => 'تحويل بنكي', 'cash' => 'نقداً', 'card' => 'بطاقة'][$o->payment_method] }}</td>
          <td class="p-3 text-center num font-semibold">{{ number_format($o->total, 2) }}</td>
          <td class="p-3">@if ($o->status === 'pending_transfer')@can('orders.verify')<a href="{{ route('admin.orders.show', $o) }}#verify" class="inline-flex h-9 px-3 items-center rounded-lg bg-brand text-ink text-xs font-bold whitespace-nowrap">تحقق من التحويل</a>@endcan @endif</td>
        </tr>
      @empty
        <tr><td colspan="9" class="p-8 text-center text-neutral-500">لا توجد طلبات.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</form>
<div class="mt-4">{{ $orders->links() }}</div>
@endsection
