@extends('layouts.admin')
@section('title', 'لوحة التحكم')
@section('heading', 'نظرة عامة')
@section('content')
@php $f = fn ($v) => number_format($v, 2); @endphp
<div class="grid gap-4 grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 mb-6">
  <div class="bg-ink text-white rounded-2xl p-5 sm:col-span-2 xl:col-span-2">
    <div class="text-sm text-white/70">مبيعات اليوم · {{ $todayCount }} طلب</div>
    @if ($money)
      <div class="font-kufi text-4xl font-extrabold num mt-1">{{ $f($todayTotal) }} <span class="text-lg text-brand">ر.س</span></div>
      <div class="text-sm text-white/70 mt-2">صافي الربح التقديري: <span class="num text-white font-semibold">{{ $f($profit) }}</span> ر.س</div>
    @else
      <div class="text-white/60 mt-3 text-sm">الأرقام المالية مخفية لدورك الحالي.</div>
    @endif
  </div>
  <a href="{{ route('admin.orders.index', ['status' => 'pending_transfer']) }}" class="bg-white rounded-2xl p-5 border border-line hover:border-ink">
    <div class="text-sm text-neutral-600">بانتظار التحقق من التحويل</div>
    <div class="font-kufi text-4xl font-extrabold num mt-1 {{ $pendingTransfers ? 'text-brandDark' : '' }}">{{ $pendingTransfers }}</div>
  </a>
  <a href="{{ route('admin.inventory.index', ['status' => 'out']) }}" class="bg-white rounded-2xl p-5 border border-line hover:border-ink">
    <div class="text-sm text-neutral-600">أصناف نفدت</div>
    <div class="font-kufi text-4xl font-extrabold num mt-1">{{ $outOfStock }}</div>
  </a>
</div>

<div class="grid gap-4 xl:grid-cols-3 mb-6">
  @if ($money)
  <section class="bg-white rounded-2xl p-5 border border-line xl:col-span-2" aria-labelledby="chart-h">
    <div class="flex items-center justify-between mb-4">
      <h2 id="chart-h" class="font-bold">المبيعات آخر 7 أيام</h2>
      <div class="flex gap-4 text-xs"><span class="flex items-center gap-1"><i class="w-3 h-3 rounded-sm" style="background:#3B5BDB"></i>أونلاين</span><span class="flex items-center gap-1"><i class="w-3 h-3 rounded-sm" style="background:#D95A00"></i>المعرض</span></div>
    </div>
    <div class="flex items-end gap-3 h-48" role="img" aria-label="أعمدة المبيعات اليومية">
      @foreach ($days as $d)
        @php $on = $d['online'] / $max * 100; $sh = $d['showroom'] / $max * 100; @endphp
        <div class="flex-1 flex flex-col items-center gap-1 h-full justify-end" title="أونلاين {{ $f($d['online']) }} · المعرض {{ $f($d['showroom']) }}">
          <div class="w-full flex flex-col justify-end h-full">
            <div style="height:{{ $sh }}%;background:#D95A00" class="w-full rounded-t"></div>
            <div style="height:{{ $on }}%;background:#3B5BDB" class="w-full"></div>
          </div>
          <span class="text-xs text-neutral-600">{{ $d['label'] }}</span>
        </div>
      @endforeach
    </div>
    <table class="mt-4 w-full text-sm" aria-label="جدول المبيعات">
      <thead><tr class="text-neutral-500 text-xs"><th class="text-right font-medium py-1">اليوم</th><th class="text-right font-medium">أونلاين</th><th class="text-right font-medium">المعرض</th></tr></thead>
      <tbody>@foreach ($days as $d)<tr class="border-t border-line"><td class="py-1.5">{{ $d['label'] }}</td><td class="num">{{ $f($d['online']) }}</td><td class="num">{{ $f($d['showroom']) }}</td></tr>@endforeach</tbody>
    </table>
  </section>
  @endif

  <section class="bg-white rounded-2xl p-5 border border-line {{ $money ? '' : 'xl:col-span-3' }}">
    <h2 class="font-bold mb-3">حالة الطلبات</h2>
    <ul class="space-y-2">
      @foreach (config('dp.order_statuses') as $k => $label)
        <li><a href="{{ route('admin.orders.index', ['status' => $k]) }}" class="flex items-center justify-between min-h-[40px] px-3 rounded-xl hover:bg-paper"><span class="text-sm">{{ $label }}</span><span class="num font-bold">{{ $statusCounts[$k] ?? 0 }}</span></a></li>
      @endforeach
    </ul>
  </section>
</div>

<div class="grid gap-4 lg:grid-cols-2">
  <section class="bg-white rounded-2xl p-5 border border-line">
    <div class="flex items-center justify-between mb-3"><h2 class="font-bold">مخزون منخفض</h2><a class="text-sm text-brandDark font-semibold" href="{{ route('admin.inventory.index', ['status' => 'low']) }}">عرض الكل</a></div>
    @forelse ($low as $i)
      <div class="flex items-center justify-between py-2 border-t border-line first:border-0 text-sm">
        <span><span class="font-mono text-xs text-neutral-500" dir="ltr">{{ $i->part->sku }}</span><br>{{ $i->part->name_ar }}</span>
        <span class="num font-bold {{ $i->available <= 0 ? 'text-red-700' : 'text-brandDark' }}">{{ $i->available }}</span>
      </div>
    @empty <p class="text-sm text-neutral-500">لا توجد أصناف منخفضة.</p> @endforelse
  </section>
  <section class="bg-white rounded-2xl p-5 border border-line">
    <div class="flex items-center justify-between mb-3"><h2 class="font-bold">آخر الطلبات</h2>@can('orders.view')<a class="text-sm text-brandDark font-semibold" href="{{ route('admin.orders.index') }}">عرض الكل</a>@endcan</div>
    @foreach ($recent as $o)
      <a @can('orders.view') href="{{ route('admin.orders.show', $o) }}" @endcan class="flex items-center justify-between py-2 border-t border-line first:border-0 text-sm">
        <span><span class="font-mono" dir="ltr">{{ $o->number }}</span><br><span class="text-xs text-neutral-500">{{ $o->status_label }}</span></span>
        @if ($money)<span class="num font-semibold">{{ $f($o->total) }}</span>@endif
      </a>
    @endforeach
  </section>
</div>
@endsection
